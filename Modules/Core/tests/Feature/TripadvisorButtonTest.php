<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Core\Models\SiteSetting;
use Modules\Localization\Models\Locale;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    Permission::findOrCreate('settings.manage');
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('settings.manage');
});

it('shows no Tripadvisor button until a link is saved', function (): void {
    $this->get('/en')->assertOk()->assertDontSee('tripadvisor-float', false);
});

it('lets staff set the Tripadvisor link, which then floats on every page', function (): void {
    $link = 'https://www.tripadvisor.com/Attraction_Review-g297896-Happy_Journy_TukTuk-Negombo.html';

    $this->actingAs($this->admin)->post('/control-panel/branding', ['tripadvisor_url' => $link])->assertSessionHasNoErrors();

    expect(SiteSetting::current()->tripadvisor_url)->toBe($link);
    $this->get('/en')->assertOk()->assertSee('tripadvisor-float', false)->assertSee($link, false);
    $this->get('/en/faq')->assertOk()->assertSee($link, false);

    $this->actingAs($this->admin)->post('/control-panel/branding', ['tripadvisor_url' => ''])->assertSessionHasNoErrors();
    $this->get('/en')->assertDontSee('tripadvisor-float', false);
});

it('only accepts a real Tripadvisor link', function (): void {
    foreach (['https://evil.example/tripadvisor.com', 'http://www.tripadvisor.com/x', 'https://tripadvisor.com.evil.io/x'] as $bad) {
        $this->actingAs($this->admin)->post('/control-panel/branding', ['tripadvisor_url' => $bad])->assertSessionHasErrors('tripadvisor_url');
    }

    $this->actingAs($this->admin)->post('/control-panel/branding', ['tripadvisor_url' => 'https://www.tripadvisor.co.uk/Attraction_Review-x'])->assertSessionHasNoErrors();
});
