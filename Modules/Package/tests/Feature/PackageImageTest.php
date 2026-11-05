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

function savePackagePhotos(Package $package, array $files): void
{
    test()->actingAs(test()->manager)->put("/control-panel/packages/{$package->id}", [
        'name' => ['en' => $package->name],
        'pricing_model' => $package->pricing_model,
        'min_days' => $package->min_days,
        'sort_order' => 0,
        ...$files,
    ])->assertSessionHasNoErrors()->assertRedirect("/control-panel/packages/{$package->id}/edit");
}

function photo(string $name = 'photo.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 800, 600);
}

it('uploads a cover and related photos separately, cover first', function (): void {
    $package = Package::factory()->create();

    savePackagePhotos($package, ['images' => [photo(), photo()], 'cover_image' => photo()]);

    $images = PackageImage::query()->where('package_id', $package->id)->orderBy('id')->get();

    expect($images)->toHaveCount(3)
        ->and($images->where('is_primary', true))->toHaveCount(1)
        ->and($images[0]->is_primary)->toBeTrue();

    $fresh = Package::query()->findOrFail($package->id);

    expect($fresh->primaryImage()->id)->toBe($images[0]->id)
        ->and($fresh->galleryImages()->pluck('id')->all())->toBe($images->pluck('id')->all());

    foreach ($images as $image) {
        Storage::disk('public')->assertExists($image->path);
        Storage::disk('public')->assertExists(str_replace('.webp', '-thumb.webp', $image->path));
    }
});

it('takes a cover image when creating a package', function (): void {
    $category = Modules\Package\Models\ProductCategory::query()->where('kind', Package::KIND_VEHICLE)->firstOrFail();

    $this->actingAs($this->manager)->post('/control-panel/packages', [
        'name' => ['en' => 'New With Photos'], 'product_category_id' => $category->id, 'charge' => 'per_day', 'price' => '30', 'price_eur' => '27', 'is_active' => '1',
        'images' => [photo()], 'cover_image' => photo(),
    ])->assertSessionHasNoErrors();

    $package = Package::query()->where('name->en', 'New With Photos')->firstOrFail();

    expect($package->images)->toHaveCount(2)
        ->and($package->images->first()->is_primary)->toBeTrue()
        ->and($package->images->last()->is_primary)->toBeFalse();
});

it('makes a new cover the cover, keeping the old one among the photos', function (): void {
    $package = Package::factory()->create();

    savePackagePhotos($package, ['cover_image' => photo()]);
    $old = PackageImage::query()->where('package_id', $package->id)->sole();

    savePackagePhotos($package, ['cover_image' => photo()]);
    $fresh = Package::query()->findOrFail($package->id);

    expect($fresh->images)->toHaveCount(2)
        ->and($fresh->images->where('is_primary', true))->toHaveCount(1)
        ->and($fresh->primaryImage()->id)->not->toBe($old->id)
        ->and($fresh->galleryImages()->last()->id)->toBe($old->id);
});

it('lets staff choose any photo as the cover, but not another package\'s', function (): void {
    $package = Package::factory()->create();
    $other = Package::factory()->create();

    savePackagePhotos($package, ['images' => [photo(), photo()]]);
    [$first, $second] = PackageImage::query()->where('package_id', $package->id)->orderBy('id')->get();

    expect($first->is_primary)->toBeTrue();

    $this->get("/control-panel/packages/{$package->id}/edit")->assertOk()->assertSee('Cover')->assertSee('Make cover');

    $this->put("/control-panel/packages/{$other->id}/images/{$second->id}/cover")->assertNotFound();
    expect($second->refresh()->is_primary)->toBeFalse();

    $this->put("/control-panel/packages/{$package->id}/images/{$second->id}/cover")->assertRedirect("/control-panel/packages/{$package->id}/edit");

    expect($first->refresh()->is_primary)->toBeFalse()
        ->and($second->refresh()->is_primary)->toBeTrue();
});

it('passes the cover on to the next photo when the cover is removed', function (): void {
    $package = Package::factory()->create();

    savePackagePhotos($package, ['images' => [photo(), photo()]]);
    [$cover, $next] = PackageImage::query()->where('package_id', $package->id)->orderBy('id')->get();

    $this->delete("/control-panel/packages/{$package->id}/images/{$cover->id}");

    expect($next->refresh()->is_primary)->toBeTrue();
});

it('rejects a cover that is not a photo, or a file over 8 MB', function (): void {
    $package = Package::factory()->create();
    $form = ['name' => ['en' => $package->name], 'pricing_model' => $package->pricing_model, 'min_days' => $package->min_days, 'sort_order' => 0];

    $this->actingAs($this->manager)->put("/control-panel/packages/{$package->id}", [...$form, 'cover_image' => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf')])->assertSessionHasErrors('cover_image');
    $this->put("/control-panel/packages/{$package->id}", [...$form, 'cover_image' => UploadedFile::fake()->image('huge.jpg')->size(9000)])->assertSessionHasErrors('cover_image');

    expect(PackageImage::query()->where('package_id', $package->id)->exists())->toBeFalse();
});

it('blocks a user without packages.manage from changing the cover', function (): void {
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('Package Viewer')->syncPermissions(['packages.view']));
    $package = Package::factory()->create();

    savePackagePhotos($package, ['images' => [photo(), photo()]]);
    $second = PackageImage::query()->where('package_id', $package->id)->orderByDesc('id')->firstOrFail();

    $this->actingAs($viewer)->put("/control-panel/packages/{$package->id}/images/{$second->id}/cover")->assertForbidden();

    expect($second->refresh()->is_primary)->toBeFalse();
});

it('shows customers the cover first and all the photos wherever the package appears', function (): void {
    Modules\Localization\Models\Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    $category = Modules\Package\Models\ProductCategory::query()->where('kind', Package::KIND_VEHICLE)->firstOrFail();
    $package = Package::factory()->create(['name' => ['en' => 'Island Tour'], 'product_category_id' => $category->id, 'is_featured' => true, 'min_days' => 1, 'max_days' => null, 'pricing_model' => Package::MODEL_PER_DAY]);
    $package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 20, 'price_eur' => 18]);

    $photos = app(Modules\Package\Services\PackageImageService::class);
    $cover = $photos->store($package, photo(), isPrimary: true);
    $second = $photos->store($package, photo());
    $third = $photos->store($package, photo());

    $inOrder = e(json_encode([$cover->url(), $second->url(), $third->url()]));

    $this->get('/en/packages')->assertOk()->assertSee($inOrder, false)->assertSee('3 photos');
    $this->get('/en')->assertOk()->assertSee($inOrder, false);

    $this->get('/en/packages/'.$package->slugFor('en'))
        ->assertOk()
        ->assertSee($inOrder, false)
        ->assertSee($second->thumbUrl(), false)
        ->assertSee('data-gallery-start="2"', false)
        ->assertSee('data-gallery-dialog', false)
        ->assertSee('assets/js/package-gallery.js', false);

    // A different cover comes first.
    $photos->setCover($third);

    $this->get('/en/packages')->assertOk()->assertSee(e(json_encode([$third->url(), $cover->url(), $second->url()])), false);
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
