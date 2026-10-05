<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;
use Modules\Package\Support\DefaultProductCategories;

beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    $this->seed(AdminDatabaseSeeder::class);
    config(['admin.two_factor_enforced_roles' => []]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->tukTuks = ProductCategory::query()->where('kind', Package::KIND_VEHICLE)->firstOrFail();
    $this->stays = ProductCategory::query()->where('kind', Package::KIND_STAY)->firstOrFail();
    $this->surfing = ProductCategory::query()->where('name->en', 'Surfing')->firstOrFail();
});

function packageFormPayload(array $overrides = []): array
{
    return array_merge([
        'name' => ['en' => 'New Package'],
        'pricing_model' => Package::MODEL_PER_DAY,
        'min_days' => 1,
        'sort_order' => 0,
    ], $overrides);
}

it('starts with the four default categories', function (): void {
    $this->actingAs($this->admin)->get('/control-panel/package-categories')
        ->assertOk()
        ->assertSee('Tuk Tuk Rental')
        ->assertSee('Stays')
        ->assertSee('Surfing')
        ->assertSee('Kitesurfing');
});

it('lets an Admin create a category with a cover photo', function (): void {
    Storage::fake('public');

    $this->actingAs($this->admin)->post('/control-panel/package-categories', [
        'name' => ['en' => 'Yoga Retreats'],
        'kind' => Package::KIND_ACTIVITY,
        'sort_order' => 5,
        'is_active' => '1',
        'image' => UploadedFile::fake()->image('yoga.jpg', 1200, 800),
    ])->assertRedirect('/control-panel/package-categories');

    $category = ProductCategory::query()->where('name->en', 'Yoga Retreats')->firstOrFail();

    expect($category->isActivity())->toBeTrue()
        ->and($category->slugFor('en'))->toBe('yoga-retreats');
    Storage::disk('public')->assertExists($category->image_path);
});

it('lets a Booking Agent see categories but not change them', function (): void {
    $agent = User::factory()->create();
    $agent->assignRole('Booking Agent');

    $this->actingAs($agent)->get('/control-panel/package-categories')->assertOk();
    $this->actingAs($agent)->get('/control-panel/package-categories/create')->assertForbidden();
    $this->actingAs($agent)->post('/control-panel/package-categories', ['name' => ['en' => 'X'], 'kind' => 'vehicle', 'sort_order' => 0])->assertForbidden();
});

it('locks the booking style once a category has packages', function (): void {
    Package::factory()->create(['product_category_id' => $this->tukTuks->id]);

    $this->actingAs($this->admin)->put("/control-panel/package-categories/{$this->tukTuks->id}", [
        'name' => ['en' => 'Tuk Tuk Rental'],
        'kind' => Package::KIND_ACTIVITY,
        'sort_order' => 1,
    ])->assertSessionHasErrors('kind');

    expect($this->tukTuks->fresh()->kind)->toBe(Package::KIND_VEHICLE);
});

it('only deletes a category with no packages', function (): void {
    Package::factory()->create(['product_category_id' => $this->tukTuks->id]);

    $this->actingAs($this->admin)->delete("/control-panel/package-categories/{$this->tukTuks->id}")->assertForbidden();
    $this->actingAs($this->admin)->delete("/control-panel/package-categories/{$this->surfing->id}")->assertRedirect();

    expect(ProductCategory::query()->find($this->surfing->id))->toBeNull()
        ->and(ProductCategory::query()->find($this->tukTuks->id))->not->toBeNull();
});

it('takes a package\'s booking style from its category', function (): void {
    $this->actingAs($this->admin)->post('/control-panel/packages', packageFormPayload([
        'name' => ['en' => 'Beginner Surf Lesson'],
        'product_category_id' => $this->surfing->id,
        'pricing_model' => Package::MODEL_PER_PERSON,
        'min_days' => '',
    ]))->assertSessionHasNoErrors();

    $package = Package::query()->where('name->en', 'Beginner Surf Lesson')->firstOrFail();

    expect($package->kind)->toBe(Package::KIND_ACTIVITY)
        ->and($package->isBookableOnline())->toBeFalse()
        ->and($package->eligibleVehicleIds())->toBe([]);
});

it('creates an hourly tuk tuk package as a single-day package', function (): void {
    $this->actingAs($this->admin)->post('/control-panel/packages', packageFormPayload([
        'name' => ['en' => 'City Hop'],
        'product_category_id' => $this->tukTuks->id,
        'pricing_model' => Package::MODEL_PER_HOUR,
        'min_hours' => 2,
        'max_hours' => 6,
        'min_days' => '',
    ]))->assertSessionHasNoErrors();

    $package = Package::query()->where('name->en', 'City Hop')->firstOrFail();

    expect($package->isHourly())->toBeTrue()
        ->and([$package->min_days, $package->max_days, $package->min_hours, $package->max_hours])->toBe([1, 1, 2, 6]);
});

it('refuses a pricing type that does not fit the category', function (): void {
    $this->actingAs($this->admin)->post('/control-panel/packages', packageFormPayload([
        'product_category_id' => $this->stays->id,
        'pricing_model' => Package::MODEL_PER_HOUR,
        'min_hours' => 2,
    ]))->assertSessionHasErrors('pricing_model');

    $this->actingAs($this->admin)->post('/control-panel/packages', packageFormPayload([
        'product_category_id' => $this->tukTuks->id,
        'pricing_model' => Package::MODEL_PER_PERSON,
    ]))->assertSessionHasErrors('pricing_model');
});

it('needs the included km before an extra-km rate makes sense', function (): void {
    $this->actingAs($this->admin)->post('/control-panel/packages', packageFormPayload([
        'product_category_id' => $this->tukTuks->id,
        'extra_km_rate' => '0.50',
    ]))->assertSessionHasErrors('included_km');
});

it('moves uncategorised packages into the default category for their kind', function (): void {
    $legacy = Package::factory()->create(['kind' => Package::KIND_STAY, 'product_category_id' => null]);
    Package::query()->whereKey($legacy->id)->update(['product_category_id' => null]);

    DefaultProductCategories::install();

    expect($legacy->fresh()->product_category_id)->toBe($this->stays->id)
        ->and(ProductCategory::query()->count())->toBe(4); // defaults not duplicated
});

it('renders the category and package admin screens', function (): void {
    $hourly = Package::factory()->create(['product_category_id' => $this->tukTuks->id, 'pricing_model' => Package::MODEL_PER_HOUR, 'min_hours' => 2]);

    $this->actingAs($this->admin)->get('/control-panel/package-categories/create')->assertOk()->assertSee('Booking style');
    $this->actingAs($this->admin)->get("/control-panel/package-categories/{$this->tukTuks->id}/edit")->assertOk()->assertSee('booking style is locked');
    $this->actingAs($this->admin)->get('/control-panel/packages/create?category='.$this->surfing->id)->assertOk()->assertSee('Pricing type');
    $this->actingAs($this->admin)->get("/control-panel/packages/{$hourly->id}/edit")->assertOk()->assertSee('From (hours)')->assertSee('Price per hour for this tier');
    $this->actingAs($this->admin)->get('/control-panel/packages?category='.$this->tukTuks->id)->assertOk()->assertSee($hourly->name);
});
