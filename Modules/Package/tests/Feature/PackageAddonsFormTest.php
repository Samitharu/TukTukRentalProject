<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Addon;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;

/** Add-ons are ticked on the package form itself, when creating and editing. */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    $this->seed(AdminDatabaseSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->line = ProductCategory::query()->where('name->en', 'Tuk Tuk Rental')->firstOrFail();
    $this->helmet = Addon::factory()->create(['name' => ['en' => 'Helmet'], 'is_active' => true]);
    $this->simCard = Addon::factory()->create(['name' => ['en' => 'SIM Card'], 'is_active' => true]);
});

function addonFormPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'name' => ['en' => 'Island Explorer'],
        'product_category_id' => $test->line->id,
        'charge' => 'per_day',
        'price' => '18',
        'price_eur' => '16',
    ], $overrides);
}

/** @return array<int, bool> add-on id => included in price */
function linkedAddons(Package $package): array
{
    return $package->addons()->get()->mapWithKeys(fn (Addon $addon) => [$addon->id => (bool) $addon->pivot->is_included])->all();
}

it('links add-ons while creating a package', function (): void {
    $this->actingAs($this->admin)->post('/control-panel/packages', addonFormPayload($this, [
        'offered_addon_ids' => [$this->helmet->id, $this->simCard->id],
        'included_addon_ids' => [$this->helmet->id],
    ]))->assertSessionHasNoErrors();

    expect(linkedAddons(Package::query()->sole()))->toEqualCanonicalizing([
        $this->helmet->id => true,
        $this->simCard->id => false,
    ]);
});

it('links an add-on ticked only as included in the price', function (): void {
    $this->actingAs($this->admin)->post('/control-panel/packages', addonFormPayload($this, [
        'included_addon_ids' => [$this->helmet->id],
    ]))->assertSessionHasNoErrors();

    expect(linkedAddons(Package::query()->sole()))->toBe([$this->helmet->id => true]);
});

it('changes and removes add-ons when saving the package', function (): void {
    $package = Package::factory()->create(['product_category_id' => $this->line->id]);
    $package->addons()->attach($this->helmet->id, ['is_included' => true]);

    $this->actingAs($this->admin)->put("/control-panel/packages/{$package->id}", addonFormPayload($this, [
        'offered_addon_ids' => [$this->simCard->id],
    ]))->assertSessionHasNoErrors();

    expect(linkedAddons($package))->toBe([$this->simCard->id => false]);

    $this->actingAs($this->admin)->put("/control-panel/packages/{$package->id}", addonFormPayload($this))->assertSessionHasNoErrors();

    expect(linkedAddons($package))->toBe([]);
});

it('refuses an add-on that does not exist', function (): void {
    $this->actingAs($this->admin)->post('/control-panel/packages', addonFormPayload($this, [
        'offered_addon_ids' => [999999],
    ]))->assertSessionHasErrors('offered_addon_ids.0');

    expect(Package::query()->count())->toBe(0);
});

it('offers add-ons on the create form', function (): void {
    $this->actingAs($this->admin)->get('/control-panel/packages/create')
        ->assertOk()
        ->assertSee('name="offered_addon_ids[]" value="'.$this->helmet->id.'"', false)
        ->assertSee('Helmet');
});

it('has one save button on the edit form, keeping a linked add-on that was hidden', function (): void {
    $hidden = Addon::factory()->create(['name' => ['en' => 'Old Roof Rack'], 'is_active' => false]);
    $neverLinked = Addon::factory()->create(['name' => ['en' => 'Retired Cooler'], 'is_active' => false]);
    $package = Package::factory()->create(['product_category_id' => $this->line->id]);
    $package->addons()->attach($hidden->id, ['is_included' => false]);

    $this->actingAs($this->admin)->get("/control-panel/packages/{$package->id}/edit")
        ->assertOk()
        ->assertDontSee('Save add-ons')
        ->assertSee('Old Roof Rack')
        ->assertDontSee('Retired Cooler');
});

it('remembers the ticks when the form comes back with a mistake', function (): void {
    $this->actingAs($this->admin)->from('/control-panel/packages/create')->post('/control-panel/packages', addonFormPayload($this, [
        'price' => '',
        'offered_addon_ids' => [(string) $this->helmet->id],
    ]))->assertSessionHasErrors('price');

    $this->get('/control-panel/packages/create')
        ->assertSeeInOrder(['value="'.$this->helmet->id.'"', 'checked'], false);
});
