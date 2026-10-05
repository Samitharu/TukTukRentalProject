<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Booking\Exceptions\NoVehicleAvailableException;

/**
 * Whole-checkout races, as real separate OS processes against the real
 * MySQL database (see ConcurrentHoldTest for why both are needed). Where
 * ConcurrencyStressTest races only the hold step, these race the complete
 * customer checkout — hold, booking, guest customer — exactly as the
 * public flow's confirm step runs it, including hourly rentals.
 *
 * Each attempt must end as either a booking or the graceful
 * NoVehicleAvailableException ("someone just booked it"); anything else
 * would reach the customer as an error page.
 */
function checkoutMysql(): ?Connection
{
    try {
        config(['database.connections.mysql.database' => 'tuktuk_rental']);
        DB::purge('mysql');
        $connection = DB::connection('mysql');
        $connection->select('select 1');

        return $connection;
    } catch (Throwable) {
        return null;
    }
}

/**
 * @param  array<int, array{vehicle: int, package: int, start: string, end: string, email: string, hours?: int}>  $attempts
 * @return array<int, array{success: bool, booking_id: ?int, vehicle_id: ?int, error: ?string, error_class: ?string}>
 */
function raceCheckouts(array $attempts): array
{
    $env = array_merge(getenv(), [
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '3306',
        'DB_DATABASE' => 'tuktuk_rental',
        'DB_USERNAME' => 'root',
        'DB_PASSWORD' => '',
        'APP_ENV' => 'local',
        'CACHE_STORE' => 'database',
        'SESSION_DRIVER' => 'database',
        // The "new booking" admin email runs inline into the array mailer:
        // nothing is queued into, or sent from, the real database.
        'QUEUE_CONNECTION' => 'sync',
        'MAIL_MAILER' => 'array',
    ]);

    // All processes boot first, then wait for this shared instant.
    $startAt = microtime(true) + 2.5 + count($attempts) * 0.35;
    $processes = [];

    foreach ($attempts as $i => $attempt) {
        $command = sprintf(
            '%s %s booking:attempt-concurrent-hold %d %s %s %s --package=%d --confirm --email=%s --start-at=%F%s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('artisan')),
            $attempt['vehicle'],
            escapeshellarg($attempt['start']),
            escapeshellarg($attempt['end']),
            escapeshellarg((string) Str::uuid()),
            $attempt['package'],
            escapeshellarg($attempt['email']),
            $startAt,
            isset($attempt['hours']) ? ' --hours='.$attempt['hours'] : '',
        );

        $processes[$i] = [proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env), $pipes];
    }

    $results = [];

    foreach ($processes as $i => [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $decoded = json_decode(trim(strrchr("\n".trim($stdout), "\n")), true);
        expect($decoded)->not->toBeNull("Attempt #{$i} printed no JSON. stdout: {$stdout} stderr: {$stderr}");
        $results[$i] = $decoded;
    }

    return $results;
}

/**
 * @return array{category: int, vehicles: int[], daily: int, hourly: int, tag: string}
 */
function seedCheckoutFleet(Connection $mysql, int $vehicleCount): array
{
    $now = now();
    $tag = 'race-'.Str::lower(Str::random(8));
    $category = $mysql->table('vehicle_categories')->insertGetId([
        'name' => json_encode(['en' => 'Checkout Race']), 'is_active' => 1, 'sort_order' => 0,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    $vehicles = [];
    for ($i = 0; $i < $vehicleCount; $i++) {
        $vehicles[] = $mysql->table('vehicles')->insertGetId([
            'category_id' => $category, 'name' => json_encode(['en' => "Race {$i}"]),
            'plate_no' => 'RCE-'.Str::upper(Str::random(8)), 'seats' => 3, 'transmission' => 'manual',
            'fuel_type' => 'petrol', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    $daily = $mysql->table('packages')->insertGetId([
        'name' => json_encode(['en' => 'Race Daily']), 'pricing_model' => 'per_day', 'min_days' => 1,
        'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $hourly = $mysql->table('packages')->insertGetId([
        'name' => json_encode(['en' => 'Race Hourly']), 'pricing_model' => 'per_hour', 'min_days' => 1, 'max_days' => 1,
        'min_hours' => 1, 'max_hours' => 8, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);

    foreach ([$daily => 20, $hourly => 5] as $packageId => $price) {
        $mysql->table('package_pricing_tiers')->insert(['package_id' => $packageId, 'min_days' => 1, 'max_days' => null, 'price' => $price, 'created_at' => $now, 'updated_at' => $now]);

        foreach ($vehicles as $vehicleId) {
            $mysql->table('package_vehicles')->insert(['package_id' => $packageId, 'vehicle_id' => $vehicleId]);
        }
    }

    return ['category' => $category, 'vehicles' => $vehicles, 'daily' => $daily, 'hourly' => $hourly, 'tag' => $tag];
}

function cleanupCheckoutFleet(Connection $mysql, array $fleet): void
{
    $bookingIds = $mysql->table('bookings')->whereIn('vehicle_id', $fleet['vehicles'])->pluck('id');

    $mysql->table('vehicle_reservation_slots')->whereIn('vehicle_id', $fleet['vehicles'])->delete();
    $mysql->table('booking_status_history')->whereIn('booking_id', $bookingIds)->delete();
    $mysql->table('bookings')->whereIn('id', $bookingIds)->delete();
    $mysql->table('booking_holds')->whereIn('vehicle_id', $fleet['vehicles'])->delete();
    $mysql->table('customers')->where('email', 'like', $fleet['tag'].'%')->delete();
    $mysql->table('package_pricing_tiers')->whereIn('package_id', [$fleet['daily'], $fleet['hourly']])->delete();
    $mysql->table('package_vehicles')->whereIn('package_id', [$fleet['daily'], $fleet['hourly']])->delete();
    $mysql->table('packages')->whereIn('id', [$fleet['daily'], $fleet['hourly']])->delete();
    $mysql->table('vehicles')->whereIn('id', $fleet['vehicles'])->delete();
    $mysql->table('vehicle_categories')->where('id', $fleet['category'])->delete();
}

function expectOnlyGracefulCheckoutFailures(array $results): void
{
    foreach ($results as $i => $result) {
        if (! $result['success']) {
            expect($result['error_class'])->toBe(
                NoVehicleAvailableException::class,
                "Attempt #{$i} failed with {$result['error_class']}: {$result['error']} — the customer would see an error page instead of 'just booked'."
            );
        }
    }
}

it('gives one tuk tuk to exactly one of ten customers checking out the same dates at the same instant', function (): void {
    $mysql = checkoutMysql() ?? $this->markTestSkipped('Requires the local "mysql" connection.');
    $fleet = seedCheckoutFleet($mysql, 1);
    $vehicle = $fleet['vehicles'][0];

    try {
        $attempts = array_map(fn (int $i) => [
            'vehicle' => $vehicle, 'package' => $fleet['daily'], 'start' => '2027-06-10', 'end' => '2027-06-12',
            'email' => "{$fleet['tag']}-{$i}@example.test",
        ], range(1, 10));

        $results = raceCheckouts($attempts);
        expectOnlyGracefulCheckoutFailures($results);

        $winners = array_filter($results, fn ($r) => $r['success']);
        expect($winners)->toHaveCount(1)
            ->and($mysql->table('bookings')->where('vehicle_id', $vehicle)->count())->toBe(1)
            ->and($mysql->table('vehicle_reservation_slots')->where('vehicle_id', $vehicle)->count())->toBe(3)
            // Losers leave nothing behind: no orphan hold, no orphan customer.
            ->and($mysql->table('booking_holds')->where('vehicle_id', $vehicle)->count())->toBe(1)
            ->and($mysql->table('customers')->where('email', 'like', $fleet['tag'].'%')->count())->toBe(1);
    } finally {
        cleanupCheckoutFleet($mysql, $fleet);
    }
})->group('concurrency');

it('lets only one hourly rental have a tuk tuk on a given day, whether the times overlap or not', function (): void {
    $mysql = checkoutMysql() ?? $this->markTestSkipped('Requires the local "mysql" connection.');
    $fleet = seedCheckoutFleet($mysql, 1);
    $vehicle = $fleet['vehicles'][0];

    try {
        $slots = [
            ['2027-06-20 09:00', '2027-06-20 13:00', 4], // overlap with the next one
            ['2027-06-20 11:00', '2027-06-20 15:00', 4],
            ['2027-06-20 16:00', '2027-06-20 18:00', 2], // no time overlap — still the same day
            ['2027-06-20 09:00', '2027-06-20 13:00', 4], // identical to the first
        ];
        $attempts = array_map(fn (array $s, int $i) => [
            'vehicle' => $vehicle, 'package' => $fleet['hourly'], 'start' => $s[0], 'end' => $s[1], 'hours' => $s[2],
            'email' => "{$fleet['tag']}-{$i}@example.test",
        ], $slots, array_keys($slots));

        $results = raceCheckouts($attempts);
        expectOnlyGracefulCheckoutFailures($results);

        $winner = collect($results)->firstWhere('success', true);
        $booking = $mysql->table('bookings')->where('id', $winner['booking_id'] ?? 0)->first();

        expect(array_filter($results, fn ($r) => $r['success']))->toHaveCount(1)
            ->and($mysql->table('vehicle_reservation_slots')->where('vehicle_id', $vehicle)->pluck('slot_date')->map(fn ($d) => substr((string) $d, 0, 10))->all())->toBe(['2027-06-20'])
            // The winner keeps its real times, not midnight.
            ->and(substr((string) $booking->start_at, 11, 5))->not->toBe('00:00');
    } finally {
        cleanupCheckoutFleet($mysql, $fleet);
    }
})->group('concurrency');

it('blocks a daily rental and an hourly rental racing for the same tuk tuk on the same day', function (): void {
    $mysql = checkoutMysql() ?? $this->markTestSkipped('Requires the local "mysql" connection.');
    $fleet = seedCheckoutFleet($mysql, 1);
    $vehicle = $fleet['vehicles'][0];

    try {
        $results = raceCheckouts([
            ['vehicle' => $vehicle, 'package' => $fleet['daily'], 'start' => '2027-07-01', 'end' => '2027-07-03', 'email' => "{$fleet['tag']}-day@example.test"],
            ['vehicle' => $vehicle, 'package' => $fleet['hourly'], 'start' => '2027-07-02 10:00', 'end' => '2027-07-02 12:00', 'hours' => 2, 'email' => "{$fleet['tag']}-hour@example.test"],
        ]);

        expectOnlyGracefulCheckoutFailures($results);
        expect(array_filter($results, fn ($r) => $r['success']))->toHaveCount(1)
            ->and($mysql->table('bookings')->where('vehicle_id', $vehicle)->count())->toBe(1);
    } finally {
        cleanupCheckoutFleet($mysql, $fleet);
    }
})->group('concurrency');

it('auto-assigns different tuk tuks to customers racing for the same package until the fleet is full', function (): void {
    $mysql = checkoutMysql() ?? $this->markTestSkipped('Requires the local "mysql" connection.');
    $fleet = seedCheckoutFleet($mysql, 3);

    try {
        $attempts = array_map(fn (int $i) => [
            'vehicle' => 0, 'package' => $fleet['daily'], 'start' => '2027-08-05', 'end' => '2027-08-06',
            'email' => "{$fleet['tag']}-{$i}@example.test",
        ], range(1, 6));

        $results = raceCheckouts($attempts);
        expectOnlyGracefulCheckoutFailures($results);

        $winners = collect($results)->where('success', true);
        expect($winners)->toHaveCount(3)
            ->and($winners->pluck('vehicle_id')->sort()->values()->all())->toBe($fleet['vehicles'])
            ->and($mysql->table('bookings')->whereIn('vehicle_id', $fleet['vehicles'])->count())->toBe(3);
    } finally {
        cleanupCheckoutFleet($mysql, $fleet);
    }
})->group('concurrency');

it('handles the same new customer booking two different tuk tuks at the same instant', function (): void {
    $mysql = checkoutMysql() ?? $this->markTestSkipped('Requires the local "mysql" connection.');
    $fleet = seedCheckoutFleet($mysql, 2);
    [$first, $second] = $fleet['vehicles'];
    $email = "{$fleet['tag']}-twotabs@example.test";

    try {
        // Two browser tabs, two tuk tuks, one brand-new customer: both
        // bookings are legitimate, and must share a single customer record.
        $results = raceCheckouts([
            ['vehicle' => $first, 'package' => $fleet['daily'], 'start' => '2027-09-01', 'end' => '2027-09-02', 'email' => $email],
            ['vehicle' => $second, 'package' => $fleet['daily'], 'start' => '2027-09-01', 'end' => '2027-09-02', 'email' => $email],
        ]);

        foreach ($results as $i => $result) {
            expect($result['success'])->toBeTrue("Attempt #{$i} failed with {$result['error_class']}: {$result['error']}");
        }

        expect($mysql->table('customers')->where('email', $email)->count())->toBe(1)
            ->and($mysql->table('bookings')->whereIn('vehicle_id', $fleet['vehicles'])->distinct()->count('customer_id'))->toBe(1);
    } finally {
        cleanupCheckoutFleet($mysql, $fleet);
    }
})->repeat(6)->group('concurrency');
