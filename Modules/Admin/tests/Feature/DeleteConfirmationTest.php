<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\File;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Modules\Package\Models\Addon;

/**
 * The Content-Security-Policy blocks inline handlers such as
 * onsubmit="return confirm(...)", so a delete would go through without its
 * "Are you sure?". Forms carry data-confirm instead, which admin-confirm.js
 * (loaded on every control panel page) asks.
 */
it('uses no inline event handlers in any view, since the security policy blocks them', function (): void {
    $offenders = collect(File::allFiles(base_path('Modules')))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
        ->filter(fn ($file) => preg_match('/\son[a-z]+\s*=\s*["\']/i', $file->getContents()) === 1)
        ->map(fn ($file) => str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

it('asks before removing, through the confirmation script on every control panel page', function (): void {
    $this->seed(AdminDatabaseSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Addon::factory()->create(['name' => ['en' => 'Helmet']]);

    $this->actingAs($admin)->get(route('admin.addons.index'))->assertOk()
        ->assertSee('data-confirm="Remove this add-on?"', false)
        ->assertSee('assets/js/admin-confirm.js', false);
});

it('ships the confirmation script', function (): void {
    expect(file_get_contents(public_path('assets/js/admin-confirm.js')))
        ->toContain("hasAttribute('data-confirm')")
        ->toContain('window.confirm');
});
