<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\File;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Modules\Admin\Services\DatabaseBackupService;
use Spatie\Activitylog\Models\Activity;

/**
 * The tests run on SQLite, which this backup does not support, so the download
 * uses a stand-in service and a real attempt exercises the failure path.
 */
beforeEach(function (): void {
    $this->seed(AdminDatabaseSeeder::class);

    $this->owner = User::factory()->create(['password' => 'owner-password']);
    $this->owner->assignRole('Super Admin');
    $this->admin = User::factory()->create(['password' => 'admin-password']);
    $this->admin->assignRole('Admin');

    $this->backupsDirectory = storage_path('app/private/backups');
});

function fakeBackupService(): void
{
    app()->instance(DatabaseBackupService::class, new class () extends DatabaseBackupService {
        public function create(): string
        {
            $path = tempnam(sys_get_temp_dir(), 'backup-test');
            file_put_contents($path, 'fake backup');

            return $path;
        }
    });
}

it('offers backups to a Super Admin only', function (): void {
    $this->actingAs($this->owner)->get('/control-panel/backups')
        ->assertOk()
        ->assertSee(route('admin.backups.index'), false)
        ->assertSee('Download backup');

    $this->actingAs($this->admin)->get('/control-panel')->assertOk()->assertDontSee(route('admin.backups.index'), false);
    $this->actingAs($this->admin)->get('/control-panel/backups')->assertForbidden();
});

it('sends guests to the login page', function (): void {
    $this->get('/control-panel/backups')->assertRedirect();
    $this->post('/control-panel/backups', ['password' => 'x'])->assertRedirect();

    expect(Activity::query()->inLog('backup')->count())->toBe(0);
});

it('refuses an Admin, even with the right password', function (): void {
    fakeBackupService();

    $this->actingAs($this->admin)
        ->post('/control-panel/backups', ['password' => 'admin-password'])
        ->assertForbidden();

    expect(Activity::query()->inLog('backup')->count())->toBe(0);
});

it('asks for the password again', function (string $password): void {
    fakeBackupService();

    $this->actingAs($this->owner)
        ->from('/control-panel/backups')
        ->post('/control-panel/backups', ['password' => $password])
        ->assertRedirect('/control-panel/backups')
        ->assertSessionHasErrors('password');

    expect(Activity::query()->inLog('backup')->count())->toBe(0);
})->with(['wrong' => ['not-my-password'], 'blank' => ['']]);

it('downloads the file, removes it from the server, and records who took it', function (): void {
    fakeBackupService();

    $response = $this->actingAs($this->owner)->post('/control-panel/backups', ['password' => 'owner-password']);

    $response->assertOk();
    $file = $response->baseResponse->getFile()->getPathname();

    expect($response->headers->get('Content-Disposition'))->toMatch('/attachment; filename=.*-database-\d{4}-\d{2}-\d{2}-\d{6}\.sql\.gz/')
        ->and($response->headers->get('Content-Type'))->toBe('application/gzip');

    ob_start();
    $response->baseResponse->sendContent();
    $sent = ob_get_clean();

    expect($sent)->toBe('fake backup')
        ->and(file_exists($file))->toBeFalse();

    $record = Activity::query()->inLog('backup')->sole();

    expect($record->causer->is($this->owner))->toBeTrue()
        ->and($record->getExtraProperty('bytes'))->toBe(11);

    $this->actingAs($this->owner)->get('/control-panel/backups')->assertOk()->assertSee($this->owner->name);
});

it('explains a failed backup and leaves nothing behind', function (): void {
    File::ensureDirectoryExists($this->backupsDirectory);
    $before = File::glob($this->backupsDirectory.'/*.sql.gz');

    $this->actingAs($this->owner)
        ->from('/control-panel/backups')
        ->post('/control-panel/backups', ['password' => 'owner-password'])
        ->assertRedirect('/control-panel/backups')
        ->assertSessionHasErrors('backup');

    expect(File::glob($this->backupsDirectory.'/*.sql.gz'))->toBe($before)
        ->and(Activity::query()->inLog('backup')->count())->toBe(0);
});

it('clears an old file left by an interrupted download, and keeps a recent one', function (): void {
    File::ensureDirectoryExists($this->backupsDirectory);
    $old = $this->backupsDirectory.'/test-old.sql.gz';
    $recent = $this->backupsDirectory.'/test-recent.sql.gz';
    file_put_contents($old, 'x');
    touch($old, time() - 7200);
    file_put_contents($recent, 'x');

    try {
        app(DatabaseBackupService::class)->create();
    } catch (RuntimeException) {
        // Expected on SQLite; the clear-out runs first.
    }

    expect(file_exists($old))->toBeFalse()
        ->and(file_exists($recent))->toBeTrue();

    File::delete($recent);
});
