<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Package\Models\Package;
use Modules\Package\Models\PackageImage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Package photo uploads — mirrors the existing vehicle-photo system
 * (Modules\Fleet\tests covers that one) and was added after the "admin
 * should have the option to upload images" request specifically because
 * packages had no photo support at all, unlike vehicles.
 */
beforeEach(function (): void {
    Storage::fake('public');
    Permission::findOrCreate('packages.view');
    Permission::findOrCreate('packages.manage');
    Role::findOrCreate('Package Manager')->syncPermissions(['packages.view', 'packages.manage']);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('Package Manager');
});

it('uploads a photo when updating a package and marks the first one primary', function (): void {
    $package = Package::factory()->create();

    $this->actingAs($this->manager)->put("/control-panel/packages/{$package->id}", [
        'name' => ['en' => $package->name],
        'pricing_model' => $package->pricing_model,
        'min_days' => $package->min_days,
        'sort_order' => 0,
        'images' => [UploadedFile::fake()->image('package.jpg', 800, 600)],
    ])->assertRedirect("/control-panel/packages/{$package->id}/edit");

    $image = PackageImage::query()->where('package_id', $package->id)->first();
    expect($image)->not->toBeNull()
        ->and($image->is_primary)->toBeTrue();
    Storage::disk('public')->assertExists($image->path);

    $package->refresh();
    expect($package->primaryImage()?->id)->toBe($image->id);
});

it('removes a photo and its file, but refuses to remove another package\'s photo', function (): void {
    $package = Package::factory()->create();
    $otherPackage = Package::factory()->create();

    $this->actingAs($this->manager)->put("/control-panel/packages/{$package->id}", [
        'name' => ['en' => $package->name],
        'pricing_model' => $package->pricing_model,
        'min_days' => $package->min_days,
        'sort_order' => 0,
        'images' => [UploadedFile::fake()->image('package.jpg', 800, 600)],
    ]);
    $image = PackageImage::query()->where('package_id', $package->id)->firstOrFail();

    $this->actingAs($this->manager)
        ->delete("/control-panel/packages/{$otherPackage->id}/images/{$image->id}")
        ->assertNotFound();
    expect(PackageImage::query()->find($image->id))->not->toBeNull();

    $this->actingAs($this->manager)
        ->delete("/control-panel/packages/{$package->id}/images/{$image->id}")
        ->assertRedirect("/control-panel/packages/{$package->id}/edit");

    expect(PackageImage::query()->find($image->id))->toBeNull();
    Storage::disk('public')->assertMissing($image->path);
});

it('blocks a user without packages.manage from uploading a photo', function (): void {
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('Package Viewer')->syncPermissions(['packages.view']));
    $package = Package::factory()->create();

    $this->actingAs($viewer)->put("/control-panel/packages/{$package->id}", [
        'name' => ['en' => $package->name],
        'pricing_model' => $package->pricing_model,
        'min_days' => $package->min_days,
        'sort_order' => 0,
        'images' => [UploadedFile::fake()->image('package.jpg', 800, 600)],
    ])->assertForbidden();

    expect(PackageImage::query()->where('package_id', $package->id)->exists())->toBeFalse();
});
