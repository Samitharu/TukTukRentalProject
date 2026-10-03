<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Fleet\Models\VehicleCategory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Permission::findOrCreate('fleet.view');
    Permission::findOrCreate('fleet.manage');
    Role::findOrCreate('Fleet Viewer')->syncPermissions(['fleet.view']);
    Role::findOrCreate('Fleet Manager')->syncPermissions(['fleet.view', 'fleet.manage']);
});

it('lets a fleet viewer see the vehicle list but not create one', function (): void {
    $viewer = User::factory()->create();
    $viewer->assignRole('Fleet Viewer');

    $this->actingAs($viewer)->get('/control-panel/fleet/vehicles')->assertOk();
    $this->actingAs($viewer)->get('/control-panel/fleet/vehicles/create')->assertForbidden();
});

it('blocks a user with no fleet permission entirely', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/control-panel/fleet/vehicles')->assertForbidden();
});

it('lets a fleet manager create a vehicle category', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Fleet Manager');

    $this->actingAs($manager)->post('/control-panel/fleet/categories', [
        'name' => ['en' => 'Deluxe'],
        'sort_order' => 1,
    ])->assertRedirect('/control-panel/fleet/categories');

    expect(VehicleCategory::query()->where('name->en', 'Deluxe')->exists())->toBeTrue();
});

it('refuses to delete a category that still has vehicles', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Fleet Manager');

    $category = VehicleCategory::factory()->create();
    \Modules\Fleet\Models\Vehicle::factory()->create(['category_id' => $category->id]);

    $this->actingAs($manager)
        ->delete("/control-panel/fleet/categories/{$category->id}")
        ->assertForbidden();

    expect(VehicleCategory::query()->find($category->id))->not->toBeNull();
});
