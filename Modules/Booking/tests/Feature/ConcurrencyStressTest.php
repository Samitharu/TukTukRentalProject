<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Booking\Exceptions\NoVehicleAvailableException;

/**
 * Heavier companion to ConcurrentHoldTest: many real OS processes (see
 * that file for why processes, and why the real MySQL connection) released
 * at the same instant via AttemptConcurrentHold's --start-at barrier, mixing
 * the "customer picked this exact tuk tuk" path with the auto-assign path
 * and overlapping-but-different date ranges.
 *
 * Beyond "no double booking" (the UNIQUE constraint guarantees that on its
 * own), these assert the customer-facing outcome: every loser must fail
 * with the graceful NoVehicleAvailableException — never a raw unique-key
 * violation, SlotConflictException or deadlock, which the public flow
 * would surface as a 500 — and auto-assign must never give up while an
 * eligible vehicle is still free.
 */
function stressMysql(): ?\Illuminate\Database\Connection
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
 * @param  array<int, array{vehicle: int, start: string, end: string, package?: int}>  $attempts
 * @return array<int, array{success: bool, hold_id: ?int, vehicle_id: ?int, error: ?string, error_class: ?string}>
 */
function runConcurrentHoldAttempts(array $attempts): array
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
        'QUEUE_CONNECTION' => 'database',
    ]);

    // Every process boots Laravel first, then sleeps until this shared
    // instant — booting ~10 artisan processes on Windows takes seconds.
    $startAt = microtime(true) + 2.5 + count($attempts) * 0.35;
    $processes = [];

    foreach ($attempts as $i => $attempt) {
        $command = sprintf(
            '%s %s booking:attempt-concurrent-hold %d %s %s %s --start-at=%F%s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('artisan')),
            $attempt['vehicle'],
            escapeshellarg($attempt['start']),
            escapeshellarg($attempt['end']),
            escapeshellarg((string) Str::uuid()),
            $startAt,
            isset($attempt['package']) ? ' --package='.$attempt['package'] : '',
        );

        $processes[$i] = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
        $processes[$i] = [$processes[$i], $pipes];
    }

    $results = [];

    foreach ($processes as $i => [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $lastLine = trim(strrchr("\n".trim($stdout), "\n"));
        $decoded = json_decode($lastLine, true);
        expect($decoded)->not->toBeNull("Attempt #{$i} printed no JSON. stdout: {$stdout} stderr: {$stderr}");
        $results[$i] = $decoded;
    }

    return $results;
}

/**
 * @return array{category: int, package: int, vehicles: int[]}
 */
function seedStressFleet(\Illuminate\Database\Connection $mysql, int $vehicleCount): array
{
    $now = now();
    $category = $mysql->table('vehicle_categories')->insertGetId([
        'name' => json_encode(['en' => 'Stress Test']), 'is_active' => 1, 'sort_order' => 0,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    $vehicles = [];
    for ($i = 0; $i < $vehicleCount; $i++) {
        $vehicles[] = $mysql->table('vehicles')->insertGetId([
            'category_id' => $category, 'name' => json_encode(['en' => "Stress {$i}"]),
            'plate_no' => 'STR-'.Str::upper(Str::random(8)), 'seats' => 3, 'transmission' => 'manual',
            'fuel_type' => 'petrol', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    $package = $mysql->table('packages')->insertGetId([
        'name' => json_encode(['en' => 'Stress Package']), 'min_days' => 1, 'is_active' => 1,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    foreach ($vehicles as $vehicleId) {
        $mysql->table('package_vehicles')->insert(['package_id' => $package, 'vehicle_id' => $vehicleId]);
    }

    return ['category' => $category, 'package' => $package, 'vehicles' => $vehicles];
}

function cleanupStressFleet(\Illuminate\Database\Connection $mysql, array $fleet): void
{
    $mysql->table('vehicle_reservation_slots')->whereIn('vehicle_id', $fleet['vehicles'])->delete();
    $mysql->table('booking_holds')->whereIn('vehicle_id', $fleet['vehicles'])->delete();
    $mysql->table('package_vehicles')->where('package_id', $fleet['package'])->delete();
    $mysql->table('packages')->where('id', $fleet['package'])->delete();
    $mysql->table('vehicles')->whereIn('id', $fleet['vehicles'])->delete();
    $mysql->table('vehicle_categories')->where('id', $fleet['category'])->delete();
}

function expectOnlyGracefulFailures(array $results): void
{
    foreach ($results as $i => $result) {
        if (! $result['success']) {
            expect($result['error_class'])->toBe(
                NoVehicleAvailableException::class,
                "Attempt #{$i} failed with {$result['error_class']}: {$result['error']} — a customer would see a 500 instead of 'not available'."
            );
        }
    }
}

it('fills every vehicle exactly once when direct and auto-assign bookings race for the same dates', function (int $round): void {
    $mysql = stressMysql() ?? $this->markTestSkipped('Requires the local "mysql" connection.');
    $fleet = seedStressFleet($mysql, 3);
    [$v1, $v2, $v3] = $fleet['vehicles'];

    try {
        // 3 customers want tuk tuk #2 specifically; 7 others take any
        // eligible one from the package. 3 vehicles → exactly 3 winners.
        $attempts = [];
        foreach (range(1, 3) as $_) {
            $attempts[] = ['vehicle' => $v2, 'start' => '2027-03-10', 'end' => '2027-03-12'];
        }
        foreach (range(1, 7) as $_) {
            $attempts[] = ['vehicle' => 0, 'package' => $fleet['package'], 'start' => '2027-03-10', 'end' => '2027-03-12'];
        }
        shuffle($attempts);

        $results = runConcurrentHoldAttempts($attempts);
        $winners = array_values(array_filter($results, fn ($r) => $r['success']));

        expectOnlyGracefulFailures($results);
        expect($winners)->toHaveCount(3, 'All 3 vehicles should end up booked — auto-assign must not give up while one is free.');
        expect(collect($winners)->pluck('vehicle_id')->sort()->values()->all())->toBe([$v1, $v2, $v3]);

        // 3 days × 3 vehicles, each vehicle's slots owned by a single hold.
        expect($mysql->table('vehicle_reservation_slots')->whereIn('vehicle_id', $fleet['vehicles'])->count())->toBe(9);
        foreach ($fleet['vehicles'] as $vehicleId) {
            expect($mysql->table('vehicle_reservation_slots')->where('vehicle_id', $vehicleId)->distinct()->count('holdable_id'))->toBe(1);
        }
    } finally {
        cleanupStressFleet($mysql, $fleet);
    }
})->with([1, 2, 3])->group('concurrency');

it('never double-books a day when overlapping date ranges race for one vehicle', function (): void {
    $mysql = stressMysql() ?? $this->markTestSkipped('Requires the local "mysql" connection.');
    $fleet = seedStressFleet($mysql, 1);
    $vehicle = $fleet['vehicles'][0];

    try {
        $ranges = [
            ['2027-04-10', '2027-04-12'], ['2027-04-11', '2027-04-13'], ['2027-04-12', '2027-04-14'],
            ['2027-04-09', '2027-04-10'], ['2027-04-14', '2027-04-15'], ['2027-04-13', '2027-04-13'],
            ['2027-04-10', '2027-04-12'], ['2027-04-16', '2027-04-17'],
        ];
        $attempts = array_map(fn ($r) => ['vehicle' => $vehicle, 'start' => $r[0], 'end' => $r[1]], $ranges);

        $results = runConcurrentHoldAttempts($attempts);

        expectOnlyGracefulFailures($results);

        // Every reserved day belongs to exactly one winner, and the slot
        // count equals the sum of the winners' day counts — nothing lost,
        // nothing written twice.
        $expectedDays = 0;
        foreach ($results as $i => $result) {
            if ($result['success']) {
                $expectedDays += (int) (new DateTime($ranges[$i][0]))->diff(new DateTime($ranges[$i][1]))->days + 1;
            }
        }

        expect($expectedDays)->toBeGreaterThan(0)
            ->and($mysql->table('vehicle_reservation_slots')->where('vehicle_id', $vehicle)->count())->toBe($expectedDays);

        // The one range that overlaps nothing else must always win.
        expect($results[7]['success'])->toBeTrue();
    } finally {
        cleanupStressFleet($mysql, $fleet);
    }
})->group('concurrency');
