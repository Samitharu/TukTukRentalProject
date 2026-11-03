<?php

declare(strict_types=1);

use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Localization\Models\Locale;

/**
 * Public unit pages, for units not in a product line. Vehicles and stays
 * can no longer be registered; the real lines' URLs open their packages
 * (see ProductLinesTest).
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    $this->stayCategory = VehicleCategory::factory()->stay()->create();
    $this->tukTukCategory = VehicleCategory::factory()->create();
});

it('lists stays and tuk tuks on their own public pages', function (): void {
    $cabana = Vehicle::factory()->stay()->create(['category_id' => $this->stayCategory->id, 'name' => ['en' => 'Sunset Cabana']]);
    Vehicle::factory()->create(['category_id' => $this->tukTukCategory->id, 'name' => ['en' => 'Red Tuk Tuk']]);

    $this->get('/en/stays')->assertOk()->assertSee('Sunset Cabana')->assertDontSee('Red Tuk Tuk');
    $this->get('/en/vehicles')->assertOk()->assertSee('Red Tuk Tuk')->assertDontSee('Sunset Cabana');

    $slug = $cabana->slugFor('en');

    $this->get("/en/stays/{$slug}")
        ->assertOk()
        ->assertSee('Get directions')
        ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=6.8406000,81.8368000', false)
        ->assertSee('data-location-map', false);

    $this->get("/en/tuk-tuks/{$slug}")->assertRedirect("/en/stays/{$slug}");
});
