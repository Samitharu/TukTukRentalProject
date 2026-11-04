<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Availability\Models\TransferArea;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::findOrCreate('availability.view');
    Permission::findOrCreate('availability.manage');

    $this->manager = User::factory()->create();
    $this->manager->givePermissionTo(['availability.view', 'availability.manage']);

    $this->viewer = User::factory()->create();
    $this->viewer->givePermissionTo('availability.view');
});

it('lets a manager add, edit and remove airport pickup areas', function (): void {
    $this->actingAs($this->manager)->post('/control-panel/transfer-areas', [
        'name' => ['en' => 'Colombo'],
        'price' => '45',
        'price_eur' => '40',
        'sort_order' => '1',
        'is_active' => '1',
    ])->assertRedirect('/control-panel/transfer-areas');

    $area = TransferArea::query()->sole();
    expect($area->getTranslation('name', 'en'))->toBe('Colombo')
        ->and((float) $area->price)->toBe(45.0)
        ->and((float) $area->price_eur)->toBe(40.0)
        ->and($area->duration_minutes)->toBe(120); // not asked: the default

    $this->actingAs($this->manager)->get('/control-panel/transfer-areas')->assertOk()->assertSee('Colombo')->assertDontSee('Trip duration');
    $this->actingAs($this->manager)->get('/control-panel/transfer-areas/create')->assertOk()->assertDontSee('duration_minutes');

    $this->actingAs($this->manager)->put("/control-panel/transfer-areas/{$area->id}", [
        'name' => ['en' => 'Colombo city'], 'price' => '50', 'price_eur' => '45', 'sort_order' => '1',
    ])->assertRedirect('/control-panel/transfer-areas');

    expect($area->fresh()->is_active)->toBeFalse() // unticked checkbox
        ->and((float) $area->fresh()->price)->toBe(50.0);

    $this->actingAs($this->manager)->delete("/control-panel/transfer-areas/{$area->id}")->assertRedirect();
    expect(TransferArea::query()->count())->toBe(0);
});

it('validates the price', function (): void {
    $this->actingAs($this->manager)->post('/control-panel/transfer-areas', [
        'name' => ['en' => 'Kandy'], 'price' => '-1', 'sort_order' => '0',
    ])->assertSessionHasErrors('price');
});

it('lets view-only staff see areas but not change them', function (): void {
    $area = TransferArea::factory()->create();

    $this->actingAs($this->viewer)->get('/control-panel/transfer-areas')->assertOk();
    $this->actingAs($this->viewer)->post('/control-panel/transfer-areas', ['name' => ['en' => 'X'], 'price' => 1, 'sort_order' => 0])->assertForbidden();
    $this->actingAs($this->viewer)->delete("/control-panel/transfer-areas/{$area->id}")->assertForbidden();
});
