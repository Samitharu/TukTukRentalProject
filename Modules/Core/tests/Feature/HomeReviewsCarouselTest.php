<?php

declare(strict_types=1);

use Modules\CMS\Models\Review;
use Modules\Localization\Models\Locale;

/**
 * The homepage review slider shows approved customer reviews — and must
 * never leak a review the team hasn't approved yet, since the feedback form
 * promises exactly that.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
});

it('shows approved reviews in the slider', function (): void {
    Review::query()->create(['customer_name' => 'Anna K.', 'country' => 'DE', 'rating' => 5, 'content' => 'Loved the coastal ride!', 'is_approved' => true]);

    $this->get('/en')
        ->assertOk()
        ->assertSee('data-review-carousel', false)
        ->assertSee('Loved the coastal ride!')
        ->assertSee('Anna K.')
        ->assertSee('AK');
});

it('no longer has a testimonials admin page', function (): void {
    $this->get('/control-panel/testimonials')->assertNotFound();
});

it('hides unapproved and comment-less reviews', function (): void {
    Review::query()->create(['customer_name' => 'Pending P.', 'rating' => 5, 'content' => 'Not approved yet', 'is_approved' => false]);
    Review::query()->create(['customer_name' => 'Silent S.', 'rating' => 5, 'content' => '', 'is_approved' => true]);

    $this->get('/en')
        ->assertOk()
        ->assertDontSee('Not approved yet')
        ->assertDontSee('Silent S.')
        ->assertDontSee('data-review-carousel', false);
});
