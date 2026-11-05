<?php

declare(strict_types=1);

use Modules\Localization\Models\Locale;

beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    config(['core.business.phone' => '+94 77 111 1111']);
});

it('shows one phone number when no second one is set', function (): void {
    config(['core.business.phone_2' => '']);

    $this->get('/en')->assertOk()
        ->assertSee('tel:+94 77 111 1111', false)
        ->assertDontSee('+94 77 111 1111</a>'."\n".'                                                    |', false);
});

it('shows a second phone number beside the first, each one tappable', function (): void {
    config(['core.business.phone_2' => '+94 77 222 2222']);

    $this->get('/en')->assertOk()
        ->assertSeeInOrder(['tel:+94 77 111 1111', '|', 'tel:+94 77 222 2222'], false)
        ->assertSee('>+94 77 222 2222</a>', false);
});
