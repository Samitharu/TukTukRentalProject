<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Availability\Models\BusinessLocation;
use Modules\Availability\Services\AvailabilityService;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingHold;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Opt-in load test against a LARGE dataset on real MySQL:
 *
 *     RUN_PERFORMANCE_TESTS=1 php artisan test --group=performance
 *
 * Seeds (once — reused on later runs) a separate scratch database,
 * `tuktuk_perf`, through its own `perf` connection: the app's real
 * database is never touched (guarded below). ~400 tuk tuks, 60 packages,
 * 60k customers, ~120k bookings with ~350k reservation-slot rows, 30k
 * reviews and 8k expired holds — decades of trading for a real fleet.
 *
 * Then measures, per page/operation: wall time, query count and peak PHP
 * memory, asserts budgets, and writes the table to
 * storage/logs/performance-report.txt.
 */
const PERF_DATABASE = 'tuktuk_perf';
const PERF_VEHICLES = 400;
const PERF_PACKAGES = 60;
const PERF_CUSTOMERS = 60_000;
const PERF_REVIEWS = 30_000;
const PERF_EXPIRED_HOLDS = 8_000;

function perfUseScratchDatabase(): void
{
    $base = config('database.connections.mysql');
    $base['database'] = PERF_DATABASE;

    $server = new PDO("mysql:host={$base['host']};port={$base['port']}", $base['username'], $base['password']);
    $server->exec('CREATE DATABASE IF NOT EXISTS `'.PERF_DATABASE.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    config(['database.connections.perf' => $base, 'database.default' => 'perf']);
    DB::purge('perf');
    DB::setDefaultConnection('perf');

    if (DB::connection('perf')->getDatabaseName() !== PERF_DATABASE) {
        throw new RuntimeException('Refusing to run: perf connection is not pointed at the scratch database.');
    }
}

function perfSeedIfNeeded(): void
{
    $db = DB::connection('perf');

    // booking_holds is seeded last: if it's complete, so is everything else
    // (an interrupted seed is redone from scratch).
    if ($db->getSchemaBuilder()->hasTable('booking_holds') && $db->table('booking_holds')->count() >= PERF_EXPIRED_HOLDS) {
        return;
    }

    Artisan::call('migrate:fresh', ['--database' => 'perf', '--force' => true]);
    $now = now()->toDateTimeString();

    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    $locations = BusinessLocation::factory()->count(3)->create(['is_active' => true]);
    $categories = VehicleCategory::factory()->count(8)->create();

    $vehicleRows = [];
    for ($i = 1; $i <= PERF_VEHICLES; $i++) {
        $vehicleRows[] = [
            'id' => $i, 'category_id' => $categories[$i % 8]->id, 'name' => json_encode(['en' => "Tuk Tuk {$i}"]),
            'plate_no' => sprintf('PERF-%05d', $i), 'seats' => 3, 'transmission' => 'manual', 'fuel_type' => 'petrol',
            'status' => $i % 40 === 0 ? 'maintenance' : 'active', 'created_at' => $now, 'updated_at' => $now,
        ];
    }
    $db->table('vehicles')->insert($vehicleRows);

    for ($p = 1; $p <= PERF_PACKAGES; $p++) {
        $package = Package::factory()->create(['min_days' => 1 + ($p % 3), 'max_days' => $p % 5 === 0 ? 30 : null, 'sort_order' => $p]);
        $package->pricingTiers()->create(['min_days' => $package->min_days, 'max_days' => null, 'price' => 15 + $p % 10]);

        if ($p % 3 === 0) {
            $package->vehicles()->attach(range(($p * 5) % PERF_VEHICLES + 1, ($p * 5) % PERF_VEHICLES + 20));
        } elseif ($p % 3 === 1) {
            $package->categories()->attach([$categories[$p % 8]->id, $categories[($p + 1) % 8]->id]);
        }
    }

    foreach (array_chunk(range(1, PERF_CUSTOMERS), 2000) as $chunk) {
        $db->table('customers')->insert(array_map(fn (int $i) => [
            'id' => $i, 'email' => "customer{$i}@perf.test", 'full_name' => "Customer Number{$i}",
            'phone' => '+94 77 '.str_pad((string) $i, 7, '0', STR_PAD_LEFT), 'nationality' => ['GB', 'DE', 'FR', 'RU', 'US'][$i % 5],
            'created_at' => $now, 'updated_at' => $now,
        ], $chunk));
    }

    // Back-to-back bookings per vehicle, 2023-01 → 2027-06: rentals of 1-6
    // days with 0-3 day gaps, so slot dates never collide (UNIQUE holds).
    $morph = (new Booking())->getMorphClass();
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $bookingId = 0;
    $bookings = $slots = $history = [];
    $flush = function () use ($db, &$bookings, &$slots, &$history): void {
        if ($bookings) {
            $db->table('bookings')->insert($bookings);
        }
        foreach (array_chunk($slots, 4000) as $chunk) {
            $db->table('vehicle_reservation_slots')->insert($chunk);
        }
        if ($history) {
            $db->table('booking_status_history')->insert($history);
        }
        $bookings = $slots = $history = [];
    };

    mt_srand(42);
    $today = CarbonImmutable::today();
    for ($v = 1; $v <= PERF_VEHICLES; $v++) {
        $cursor = CarbonImmutable::parse('2023-01-01')->addDays(mt_rand(0, 5));
        // Leave most of the near future free so booking flows can succeed.
        $stop = $v % 4 === 0 ? CarbonImmutable::parse('2027-06-30') : $today->subDays(2);

        while ($cursor->lt($stop)) {
            $length = mt_rand(1, 6);
            $end = $cursor->addDays($length - 1);
            $bookingId++;
            $cancelled = mt_rand(1, 20) === 1;
            $status = $cancelled ? 'cancelled' : ($end->lt($today) ? 'completed' : 'confirmed');

            $code = '';
            for ($n = $bookingId, $k = 0; $k < 8; $k++, $n = intdiv($n, 32)) {
                $code .= $alphabet[$n % 32];
            }

            $bookings[] = [
                'id' => $bookingId, 'reference' => 'MTR-'.$code, 'customer_id' => mt_rand(1, PERF_CUSTOMERS), 'vehicle_id' => $v,
                'package_id' => mt_rand(1, PERF_PACKAGES), 'business_location_id' => $locations[0]->id, 'pickup_type' => 'office',
                'start_at' => $cursor->toDateTimeString(), 'end_at' => $end->toDateTimeString(), 'status' => $status,
                'price_breakdown' => '{}', 'total_amount' => 20 * $length, 'currency_code' => 'USD',
                'idempotency_key' => 'perf-'.$bookingId, 'created_at' => $cursor->subDays(mt_rand(1, 60))->toDateTimeString(), 'updated_at' => $now,
            ];
            $history[] = ['booking_id' => $bookingId, 'to_status' => $status, 'reason' => 'Seeded.', 'created_at' => $now];

            if (! $cancelled) {
                for ($d = $cursor; $d->lte($end); $d = $d->addDay()) {
                    $slots[] = ['vehicle_id' => $v, 'slot_date' => $d->toDateString(), 'holdable_type' => $morph, 'holdable_id' => $bookingId, 'created_at' => $now];
                }
            }

            $cursor = $end->addDays(1 + mt_rand(0, 3));

            if (count($bookings) >= 1500) {
                $flush();
            }
        }
    }
    $flush();

    foreach (array_chunk(range(1, PERF_REVIEWS), 2000) as $chunk) {
        $db->table('reviews')->insert(array_map(fn (int $i) => [
            'booking_id' => mt_rand(1, $bookingId), 'customer_name' => "Guest {$i}", 'country' => 'GB', 'rating' => 3 + $i % 3,
            'content' => $i % 7 === 0 ? '' : "Review {$i}: lovely ride along the coast, friendly team and a reliable tuk tuk.",
            'is_approved' => $i % 2 === 0, 'created_at' => CarbonImmutable::parse('2023-01-01')->addMinutes($i * 60)->toDateTimeString(), 'updated_at' => $now,
        ], $chunk));
    }

    // Abandoned checkouts the scheduler hasn't swept yet, each still
    // occupying 2 slot-days far in the future (2029+, clear of bookings).
    $holdMorph = (new BookingHold())->getMorphClass();
    foreach (array_chunk(range(1, PERF_EXPIRED_HOLDS), 1000) as $chunk) {
        $holds = $holdSlots = [];
        foreach ($chunk as $h) {
            $vehicle = ($h % PERF_VEHICLES) + 1;
            $start = CarbonImmutable::parse('2029-01-01')->addDays(intdiv($h, PERF_VEHICLES) * 3);
            $holds[] = ['id' => $h, 'hold_key' => 'perf-hold-'.$h, 'vehicle_id' => $vehicle, 'start_at' => $start, 'end_at' => $start->addDay(),
                'expires_at' => now()->subHour(), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now];
            $holdSlots[] = ['vehicle_id' => $vehicle, 'slot_date' => $start->toDateString(), 'holdable_type' => $holdMorph, 'holdable_id' => $h, 'created_at' => $now];
            $holdSlots[] = ['vehicle_id' => $vehicle, 'slot_date' => $start->addDay()->toDateString(), 'holdable_type' => $holdMorph, 'holdable_id' => $h, 'created_at' => $now];
        }
        $db->table('booking_holds')->insert($holds);
        $db->table('vehicle_reservation_slots')->insert($holdSlots);
    }

    $db->statement('ANALYZE TABLE bookings, vehicle_reservation_slots, customers, reviews, booking_holds');
}

/**
 * @return array{ms: float, queries: int, memory_mb: float, status: int|null}
 */
function perfMeasure(Closure $operation): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    gc_collect_cycles();
    memory_reset_peak_usage();
    $before = memory_get_usage();
    $started = hrtime(true);

    $result = $operation();

    $ms = (hrtime(true) - $started) / 1e6;
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return [
        'ms' => round($ms, 1),
        'queries' => $queries,
        'memory_mb' => round((memory_get_peak_usage() - $before) / 1048576, 1),
        'status' => $result instanceof \Illuminate\Testing\TestResponse ? $result->status() : null,
    ];
}

it('stays fast, query-bounded and memory-bounded on a large dataset', function (): void {
    if (! getenv('RUN_PERFORMANCE_TESTS')) {
        $this->markTestSkipped('Opt-in: RUN_PERFORMANCE_TESTS=1 php artisan test --group=performance');
    }

    perfUseScratchDatabase();
    // Not via perfMeasure(): its query log would retain every bulk insert.
    $seedStarted = hrtime(true);
    perfSeedIfNeeded();
    $seedMs = (hrtime(true) - $seedStarted) / 1e6;
    $db = DB::connection('perf');

    $counts = collect(['vehicles', 'packages', 'customers', 'bookings', 'vehicle_reservation_slots', 'reviews', 'booking_holds'])
        ->mapWithKeys(fn ($t) => [$t => $db->table($t)->count()]);
    expect($counts['bookings'])->toBeGreaterThan(80_000)
        ->and($counts['vehicle_reservation_slots'])->toBeGreaterThan(250_000);

    // Every request in a fresh app state isn't possible inside one test, so
    // warm the framework once (route/view compilation) before measuring.
    $this->get('/en');

    // Production-like queue: the confirm request only *pushes* the admin
    // email job (phpunit.xml's sync queue would render + send it inline).
    config(['queue.default' => 'database', 'queue.connections.database.connection' => 'perf']);

    $admin = User::factory()->create(['is_active' => true]);
    foreach (['bookings.view', 'bookings.manage', 'customers.view', 'cms.view'] as $permission) {
        Permission::findOrCreate($permission);
    }
    $admin->assignRole(Role::findOrCreate('Perf Manager')->syncPermissions(['bookings.view', 'bookings.manage', 'customers.view', 'cms.view']));

    $start = CarbonImmutable::today()->addDays(10);
    $results = [];
    $results['Home page (reviews slider)'] = perfMeasure(fn () => $this->get('/en'));
    $results['Fleet page'] = perfMeasure(fn () => $this->get('/en/tuk-tuks'));
    $results['Packages page'] = perfMeasure(fn () => $this->get('/en/packages'));
    $results['Booking: submit dates'] = perfMeasure(fn () => $this->post('/en/booking/start', [
        'start_date' => $start->toDateString(), 'end_date' => $start->addDays(3)->toDateString(),
        'pickup_type' => 'office', 'business_location_id' => BusinessLocation::query()->value('id'),
    ]));
    $results['Booking: package step (60 pkgs × 400 tuk tuks availability)'] = perfMeasure(fn () => $this->get('/en/booking/package'));

    $bookable = Package::query()->where('min_days', '<=', 4)->whereHas('vehicles')->value('id');
    $results['Booking: choose package'] = perfMeasure(fn () => $this->post('/en/booking/package', ['package_id' => $bookable]));
    $this->post('/en/booking/addons', []);
    $this->post('/en/booking/details', [
        'first_name' => 'Perf', 'last_name' => 'Tester', 'email' => 'perf.tester@example.test', 'phone' => '+1 555 0100',
        'nationality' => 'US', 'passport_number' => 'P0000001', 'has_valid_licence' => '1', 'has_international_permit' => '0', 'marketing_opt_in' => '0',
    ]);
    $results['Booking: review step (first render)'] = perfMeasure(fn () => $this->get('/en/booking/review'));
    $results['Booking: review step'] = perfMeasure(fn () => $this->get('/en/booking/review'));
    $results['Booking: confirm (locks + slots + queued email)'] = perfMeasure(fn () => $this->post('/en/booking/confirm', ['terms_accepted' => '1']));

    $results['Availability check ×100 (isRangeFree)'] = perfMeasure(function (): void {
        $service = app(AvailabilityService::class);
        for ($i = 1; $i <= 100; $i++) {
            $service->isRangeFree($i, CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-05'));
        }
    });

    $this->actingAs($admin);
    $results['Admin: bookings list'] = perfMeasure(fn () => $this->get('/control-panel/bookings'));
    $results['Admin: bookings filtered by status'] = perfMeasure(fn () => $this->get('/control-panel/bookings?status=confirmed'));
    $results['Admin: bookings search (name/email/ref)'] = perfMeasure(fn () => $this->get('/control-panel/bookings?q=customer5912'));
    $results['Admin: booking detail'] = perfMeasure(fn () => $this->get('/control-panel/bookings/'.Booking::query()->max('id')));
    $results['Admin: customers list'] = perfMeasure(fn () => $this->get('/control-panel/customers'));
    $results['Admin: reviews list (30k reviews)'] = perfMeasure(fn () => $this->get('/control-panel/reviews'));

    $results['Queue worker: send 1 admin email'] = perfMeasure(fn () => Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]));

    // Re-arm the seeded abandoned holds so every run measures a full sweep.
    $db->table('booking_holds')->where('hold_key', 'like', 'perf-hold-%')->update(['status' => 'active', 'expires_at' => now()->subHour()]);
    $results['Scheduler: release 8k expired holds'] = perfMeasure(fn () => Artisan::call('booking:release-expired-holds'));

    // Index usage of the hottest query: the conflict check.
    $plan = $db->select("EXPLAIN SELECT 1 FROM vehicle_reservation_slots WHERE vehicle_id = 7 AND slot_date BETWEEN '2026-03-01' AND '2026-03-05' LIMIT 1")[0];

    $lines = ['Large-dataset performance report — '.now()->toDateTimeString(), ''];
    $lines[] = 'Dataset: '.$counts->map(fn ($n, $t) => "{$t}=".number_format($n))->implode(', ');
    $lines[] = 'Seeding: '.number_format($seedMs / 1000, 1).' s (0 when reused)';
    $lines[] = "Conflict-check plan: type={$plan->type}, key={$plan->key}, rows={$plan->rows}";
    $lines[] = '';
    $lines[] = str_pad('Operation', 62).str_pad('Status', 8).str_pad('Time ms', 10).str_pad('Queries', 9).'Peak MB';
    foreach ($results as $name => $r) {
        $lines[] = str_pad($name, 62).str_pad((string) ($r['status'] ?? '-'), 8).str_pad((string) $r['ms'], 10).str_pad((string) $r['queries'], 9).$r['memory_mb'];
    }
    file_put_contents(storage_path('logs/performance-report.txt'), implode(PHP_EOL, $lines).PHP_EOL);
    fwrite(STDERR, PHP_EOL.implode(PHP_EOL, $lines).PHP_EOL);

    // ---- Budgets ----------------------------------------------------------
    expect($plan->key)->toBe('vehicle_reservation_slots_vehicle_id_slot_date_unique');

    foreach ($results as $name => $r) {
        if ($r['status'] !== null) {
            expect($r['status'])->toBeLessThan(400, "{$name} returned HTTP {$r['status']}");
        }
        expect($r['ms'])->toBeLessThan(2000, "{$name} took {$r['ms']} ms")
            ->and($r['memory_mb'])->toBeLessThan(64, "{$name} used {$r['memory_mb']} MB");
    }

    expect($results['Booking: package step (60 pkgs × 400 tuk tuks availability)']['queries'])->toBeLessThan(25)
        ->and($results['Home page (reviews slider)']['queries'])->toBeLessThan(25)
        ->and($results['Admin: bookings list']['queries'])->toBeLessThan(25)
        ->and($results['Admin: reviews list (30k reviews)']['queries'])->toBeLessThan(25)
        ->and($db->table('booking_holds')->where('status', 'active')->where('expires_at', '<', now())->count())->toBe(0);
})->group('performance');
