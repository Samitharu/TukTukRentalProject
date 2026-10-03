<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Proves the actual, final double-booking guarantee (brief §6): given two
 * genuinely simultaneous requests for the same vehicle and dates, exactly
 * one succeeds. This cannot be simulated within a single PHP process —
 * PHP is single-threaded and this environment has no pcntl (Windows) — so
 * this test spawns two real, independent `php artisan` OS processes via
 * `proc_open`, started back-to-back before either is awaited, and points
 * both at the *real* MySQL connection (not the sqlite :memory: this test
 * file itself runs under — that memory database exists only inside this
 * PHP process and isn't shared with a spawned child).
 *
 * What actually makes this safe under real concurrency is the
 * `UNIQUE(vehicle_id, slot_date)` constraint enforced by the database
 * engine itself; this test exercises that constraint under real OS-level
 * concurrency rather than merely asserting it exists.
 */
/**
 * phpunit.xml's blanket `DB_DATABASE=:memory:` override (set for the
 * default sqlite testing connection) also clobbers the `mysql` connection's
 * `database` key, since both read the same `DB_DATABASE` env var via
 * `env()` in config/database.php. This forces the real database name back
 * for just this one connection, then drops any already-cached PDO handle
 * so the corrected config actually takes effect.
 */
function useRealMysqlConnectionForConcurrencyTest(): void
{
    config(['database.connections.mysql.database' => 'tuktuk_rental']);
    DB::purge('mysql');
}

function mysqlAvailableForConcurrencyTest(): bool
{
    try {
        useRealMysqlConnectionForConcurrencyTest();
        DB::connection('mysql')->select('select 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

it('lets exactly one of two truly-simultaneous hold attempts for the same vehicle and dates succeed', function (): void {
    if (! mysqlAvailableForConcurrencyTest()) {
        $this->markTestSkipped('Requires a reachable "mysql" connection (see config/database.php) — not available in this environment.');
    }

    $mysql = DB::connection('mysql');

    $categoryId = $mysql->table('vehicle_categories')->insertGetId([
        'name' => json_encode(['en' => 'Concurrency Test']),
        'is_active' => 1,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $vehicleId = $mysql->table('vehicles')->insertGetId([
        'category_id' => $categoryId,
        'name' => json_encode(['en' => 'Concurrency Test Vehicle']),
        'plate_no' => 'CONC-'.Str::upper(Str::random(6)),
        'seats' => 3,
        'transmission' => 'manual',
        'fuel_type' => 'petrol',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $start = '2027-01-10';
    $end = '2027-01-12';
    $keyA = (string) Str::uuid();
    $keyB = (string) Str::uuid();

    // getenv() here already reflects PHPUnit's own putenv()-based overrides
    // from phpunit.xml (DB_CONNECTION=sqlite, DB_DATABASE=:memory:, etc.) —
    // every one of those must be explicitly overridden back, not just
    // DB_CONNECTION, or the child silently inherits a database name that
    // doesn't exist on the mysql server and fails before it ever reaches
    // the conflict logic this test exists to prove.
    $childEnv = array_merge(getenv(), [
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

    $buildCommand = fn (string $holdKey) => sprintf(
        '%s %s booking:attempt-concurrent-hold %d %s %s %s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(base_path('artisan')),
        $vehicleId,
        escapeshellarg($start),
        escapeshellarg($end),
        escapeshellarg($holdKey),
    );

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

    // Both processes are started here, back-to-back, before either is
    // waited on — this is what makes them genuinely concurrent rather than
    // sequential.
    $processA = proc_open($buildCommand($keyA), $descriptors, $pipesA, base_path(), $childEnv);
    $processB = proc_open($buildCommand($keyB), $descriptors, $pipesB, base_path(), $childEnv);

    expect(is_resource($processA))->toBeTrue()->and(is_resource($processB))->toBeTrue();

    $outputA = stream_get_contents($pipesA[1]);
    $stderrA = stream_get_contents($pipesA[2]);
    fclose($pipesA[1]);
    fclose($pipesA[2]);
    $exitA = proc_close($processA);

    $outputB = stream_get_contents($pipesB[1]);
    $stderrB = stream_get_contents($pipesB[2]);
    fclose($pipesB[1]);
    fclose($pipesB[2]);
    $exitB = proc_close($processB);

    $resultA = json_decode(trim(strrchr(trim($outputA), "\n") ?: $outputA), true);
    $resultB = json_decode(trim(strrchr(trim($outputB), "\n") ?: $outputB), true);

    try {
        expect($exitA)->toBe(0, "Process A failed. stderr: {$stderrA}")
            ->and($exitB)->toBe(0, "Process B failed. stderr: {$stderrB}")
            ->and($resultA)->not->toBeNull("Could not parse JSON from process A output: {$outputA}")
            ->and($resultB)->not->toBeNull("Could not parse JSON from process B output: {$outputB}");

        $successes = (int) ($resultA['success'] ?? false) + (int) ($resultB['success'] ?? false);
        expect($successes)->toBe(1, 'Expected exactly one of the two concurrent attempts to succeed.');

        $winner = $resultA['success'] ? $resultA : $resultB;

        $slotCount = $mysql->table('vehicle_reservation_slots')
            ->where('vehicle_id', $vehicleId)
            ->count();
        // 2027-01-10, 11, 12 inclusive = 3 slot-days, all owned by the winner.
        expect($slotCount)->toBe(3);

        $distinctHolders = $mysql->table('vehicle_reservation_slots')
            ->where('vehicle_id', $vehicleId)
            ->distinct()
            ->pluck('holdable_id');
        expect($distinctHolders)->toHaveCount(1)
            ->and((int) $distinctHolders->first())->toBe((int) $winner['hold_id']);
    } finally {
        $mysql->table('vehicle_reservation_slots')->where('vehicle_id', $vehicleId)->delete();
        $mysql->table('booking_holds')->where('vehicle_id', $vehicleId)->delete();
        $mysql->table('vehicles')->where('id', $vehicleId)->delete();
        $mysql->table('vehicle_categories')->where('id', $categoryId)->delete();
    }
})->group('concurrency');
