<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Availability\Models\BusinessLocation;
use Modules\Booking\Mail\BookingConfirmationMail;
use Modules\Booking\Mail\NewBookingPlacedMail;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Fleet\Models\Vehicle;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Pricing\Services\PricingService;

/**
 * "When a booking is placed, email the customer a confirmation" — queued
 * like the staff email, in the language the customer booked in.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    Locale::query()->create(['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'is_default' => false, 'is_active' => true, 'sort_order' => 2]);

    config(['booking.admin_notification_emails' => ['owner@tuktuk.test'], 'core.business.email' => 'hello@tuktuk.test']);

    $this->location = BusinessLocation::factory()->create([
        'is_active' => true,
        'address' => 'Lewis Place, Negombo',
        'google_maps_url' => 'https://maps.app.goo.gl/Office123',
        'lat' => 7.2083,
        'lng' => 79.8358,
    ]);
    $this->vehicle = Vehicle::factory()->create();
    $this->package = Package::factory()->create(['min_days' => 1]);
    $this->package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 25]);
    $this->package->vehicles()->attach($this->vehicle->id);

    $this->start = CarbonImmutable::today()->addDays(15);
});

function customerEmailBook(object $test, string $locale = 'en'): void
{
    $test->post("/{$locale}/booking/start", [
        'start_date' => $test->start->toDateString(),
        'end_date' => $test->start->addDays(2)->toDateString(),
        'pickup_type' => 'office',
        'business_location_id' => $test->location->id,
    ]);
    $test->post("/{$locale}/booking/package", ['package_id' => $test->package->id]);
    $test->post("/{$locale}/booking/addons", []);
    $test->post("/{$locale}/booking/details", [
        'first_name' => 'Kenji', 'last_name' => 'Sato', 'email' => 'kenji@example.test', 'phone' => '+81 90 1234 5678',
        'nationality' => 'JP', 'passport_number' => 'TK1234567', 'has_valid_licence' => '1',
        'has_international_permit' => '1', 'marketing_opt_in' => '0',
    ]);
    $test->get("/{$locale}/booking/review");
    $test->post("/{$locale}/booking/confirm", ['terms_accepted' => '1'])->assertRedirect();
}

it('queues one confirmation email to the customer, alongside the staff email', function (): void {
    Mail::fake();

    customerEmailBook($this);
    $booking = Booking::query()->sole();

    Mail::assertNothingSent();
    Mail::assertQueued(BookingConfirmationMail::class, 1);
    Mail::assertQueued(BookingConfirmationMail::class, fn (BookingConfirmationMail $mail) => $mail->booking->is($booking)
        && $mail->hasTo('kenji@example.test')
        && ! $mail->hasTo('owner@tuktuk.test')
        && $mail->hasReplyTo('hello@tuktuk.test')
        && $mail->locale === 'en');
    Mail::assertQueued(NewBookingPlacedMail::class, 1);
});

it('emails the customer in the language they booked in', function (): void {
    Mail::fake();

    customerEmailBook($this, 'de');

    Mail::assertQueued(BookingConfirmationMail::class, fn (BookingConfirmationMail $mail) => $mail->locale === 'de');
});

it('renders the booking details, pickup map and links back to the booking', function (): void {
    customerEmailBook($this);
    $booking = Booking::query()->sole();

    $mail = (new BookingConfirmationMail($booking))->locale('en');
    $html = $mail->render();

    expect($html)->toContain($booking->reference)
        ->toContain('Kenji Sato')
        ->toContain('Lewis Place, Negombo')
        ->toContain('https://maps.app.goo.gl/Office123')
        ->toContain('/en/booking/confirmation/'.$booking->reference)
        ->toContain('/en/booking/confirmation/'.$booking->reference.'/receipt')
        ->not->toContain('/control-panel/');
});

it('renders in German with German links', function (): void {
    customerEmailBook($this, 'de');
    $booking = Booking::query()->sole();

    $mail = (new BookingConfirmationMail($booking))->locale('de');
    $html = $mail->render();

    expect($html)->toContain('Buchung ansehen')
        ->toContain('/de/booking/confirmation/'.$booking->reference);

    // The subject is built inside the mail's locale when it is sent.
    $subject = null;
    app()->setLocale('de');
    $subject = $mail->envelope()->subject;
    app()->setLocale('en');
    expect($subject)->toBe('Buchung bestätigt — '.$booking->reference);
});

it('also confirms an admin manual booking to the customer', function (): void {
    Mail::fake();

    $price = app(PricingService::class)->calculate($this->package, $this->start, 3, [], null, null);
    app(BookingService::class)->createManualBooking(
        ['hold_key' => (string) Str::uuid(), 'start_at' => $this->start->toDateString(), 'end_at' => $this->start->addDays(2)->toDateString(), 'vehicle_id' => $this->vehicle->id, 'package_id' => $this->package->id],
        ['email' => 'walkin@example.test', 'full_name' => 'Walk In', 'pickup_type' => 'office', 'business_location_id' => $this->location->id],
        $price,
    );

    Mail::assertQueued(BookingConfirmationMail::class, fn (BookingConfirmationMail $mail) => $mail->hasTo('walkin@example.test'));
});

it('is queued after commit and retried like the staff email', function (): void {
    customerEmailBook($this);
    $mail = new BookingConfirmationMail(Booking::query()->sole());

    expect($mail)->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and($mail->tries)->toBe(5)
        ->and($mail->backoff)->toBe([60, 300, 900, 3600]);
});
