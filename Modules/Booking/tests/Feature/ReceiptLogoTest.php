<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Booking\Models\Booking;
use Modules\Core\Models\SiteSetting;
use Modules\Core\Services\SiteBrandingService;
use Modules\Customer\Models\Customer;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;

beforeEach(function (): void {
    Storage::fake('public');
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    $this->booking = Booking::factory()->create(['customer_id' => Customer::factory(), 'package_id' => Package::factory()]);
    $this->receiptUrl = "/en/booking/confirmation/{$this->booking->reference}/receipt";
});

it('puts the uploaded logo in the receipt PDF', function (): void {
    app(SiteBrandingService::class)->updateLogo(SiteSetting::current(), UploadedFile::fake()->image('logo.png', 400, 150));

    expect($this->get($this->receiptUrl)->assertOk()->getContent())
        ->toStartWith('%PDF')
        ->toContain('/Subtype /Image');
});

it('leaves the logo out when none is uploaded', function (): void {
    expect($this->get($this->receiptUrl)->assertOk()->getContent())
        ->toStartWith('%PDF')
        ->not->toContain('/Subtype /Image');
});

it('still produces the receipt when the logo file has gone', function (): void {
    SiteSetting::current()->update(['logo_path' => 'branding/missing.webp']);

    expect($this->get($this->receiptUrl)->assertOk()->getContent())
        ->toStartWith('%PDF')
        ->not->toContain('/Subtype /Image');
});

it('still produces the receipt when the logo file is unreadable', function (): void {
    Storage::disk('public')->put('branding/broken.webp', 'not an image');
    SiteSetting::current()->update(['logo_path' => 'branding/broken.webp']);

    expect($this->get($this->receiptUrl)->assertOk()->getContent())
        ->toStartWith('%PDF')
        ->not->toContain('/Subtype /Image');
});

it('fits a logo of any shape inside the box', function (int $width, int $height, int $expectedWidth, int $expectedHeight): void {
    app(SiteBrandingService::class)->updateLogo(SiteSetting::current(), UploadedFile::fake()->image('logo.png', $width, $height));

    expect(SiteSetting::current()->logoForPdf(120, 56))
        ->width->toBe($expectedWidth)
        ->height->toBe($expectedHeight)
        ->src->toStartWith('data:image/webp;base64,');
})->with([
    'wide' => [400, 100, 120, 30],
    'square' => [300, 300, 56, 56],
    'tall' => [100, 400, 14, 56],
]);
