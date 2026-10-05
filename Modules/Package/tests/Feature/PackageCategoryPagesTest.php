<?php

declare(strict_types=1);

use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;

/**
 * The public side of categories: the grouped packages page, each
 * category's own page, hiding a category, and activity packages being
 * booked on WhatsApp instead of through the booking flow.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    // The migration created the default categories before any locale
    // existed in this test database, so they have no slugs yet.
    ProductCategory::query()->get()->each->syncSlugs();

    $this->tukTuks = ProductCategory::query()->where('name->en', 'Tuk Tuk Rental')->firstOrFail();
    $this->surfing = ProductCategory::query()->where('name->en', 'Surfing')->firstOrFail();

    $this->explorer = Package::factory()->create(['name' => ['en' => 'Island Explorer'], 'product_category_id' => $this->tukTuks->id]);
    $this->explorer->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 20]);

    $this->lesson = Package::factory()->create([
        'name' => ['en' => 'Beginner Surf Lesson'],
        'product_category_id' => $this->surfing->id,
        'pricing_model' => Package::MODEL_PER_PERSON,
    ]);
    $this->lesson->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 35]);
});

it('groups packages by category and leaves out empty categories', function (): void {
    $this->get('/en/packages')
        ->assertOk()
        ->assertSeeInOrder(['Tuk Tuk Rental', 'Island Explorer', 'Surfing', 'Beginner Surf Lesson'])
        ->assertSee('/en/packages/category/surfing', false)
        ->assertDontSee('Kitesurfing');
});

it('shows a category page with its own packages', function (): void {
    $this->get('/en/packages/category/surfing')
        ->assertOk()
        ->assertSee('Beginner Surf Lesson')
        ->assertSee('/ person')
        ->assertDontSee('Island Explorer');
});

it('hides a category and its packages when the admin deactivates it', function (): void {
    $this->surfing->update(['is_active' => false]);

    $this->get('/en/packages/category/surfing')->assertNotFound();
    $this->get('/en/packages/'.$this->lesson->slugFor('en'))->assertNotFound();
    $this->get('/en/packages')->assertOk()->assertDontSee('Beginner Surf Lesson');
});

it('books an activity on WhatsApp, not through the booking flow', function (): void {
    $this->get('/en/packages/'.$this->lesson->slugFor('en'))
        ->assertOk()
        ->assertSee('Book on WhatsApp')
        ->assertSee(rawurlencode("Hi! I'd like to book: Beginner Surf Lesson"), false)
        ->assertDontSee('/en/booking/start?package='.$this->lesson->id, false);

    $this->get('/en/booking/start?package='.$this->lesson->id)->assertOk();

    expect(session('booking_flow.package_id'))->toBeNull();
});

it('keeps the normal booking button for rental packages', function (): void {
    $this->get('/en/packages/'.$this->explorer->slugFor('en'))
        ->assertOk()
        ->assertSee('/en/booking/start?package='.$this->explorer->id, false)
        ->assertDontSee('Book on WhatsApp'); // the site-wide floating chat button is a different link
});

it('lists category pages in the sitemap', function (): void {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('/en/packages/category/surfing', false)
        ->assertSee('/en/packages/category/tuk-tuk-rental', false);
});
