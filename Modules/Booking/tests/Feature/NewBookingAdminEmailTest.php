<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Availability\Models\BusinessLocation;
use Modules\Booking\Mail\NewBookingPlacedMail;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Fleet\Models\Vehicle;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Pricing\Services\PricingService;

/**
 * "When a booking is placed, email the admin" — and do it through the
 * queue, only after the booking has really been committed, exactly once.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    config(['booking.admin_notification_emails' => ['owner@tuktuk.test', 'desk@tuktuk.test']]);

    $this->location = BusinessLocation::factory()->create(['is_active' => true]);
    $this->vehicle = Vehicle::factory()->create();
    $this->package = Package::factory()->create(['min_days' => 1]);
    $this->package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 25]);
    $this->package->vehicles()->attach($this->vehicle->id);

    $this->start = CarbonImmutable::today()->addDays(15);
});

function emailTestBook(object $test, array $driver = [], ?Closure $beforeConfirm = null): \Illuminate\Testing\TestResponse
{
    $test->post('/en/booking/start', [
        'start_date' => $test->start->toDateString(),
        'end_date' => $test->start->addDays(2)->toDateString(),
        'pickup_type' => 'office',
        'business_location_id' => $test->location->id,
    ]);
    $test->post('/en/booking/package', ['package_id' => $test->package->id]);
    $test->post('/en/booking/addons', []);
    $test->post('/en/booking/details', array_merge([
        'first_name' => 'Kenji', 'last_name' => 'Sato', 'email' => 'kenji@example.test', 'phone' => '+81 90 1234 5678',
        'nationality' => 'JP', 'passport_number' => 'TK1234567', 'has_valid_licence' => '1',
        'has_international_permit' => '1', 'marketing_opt_in' => '0', 'special_requests' => 'Child seat please',
    ], $driver));
    $test->get('/en/booking/review');

    if ($beforeConfirm !== null) {
        $beforeConfirm();
    }

    return $test->post('/en/booking/confirm', ['terms_accepted' => '1']);
}

function emailTestManualBooking(object $test, string $holdKey): Booking
{
    $price = app(PricingService::class)->calculate($test->package, $test->start, 3, [], null, null);

    return app(BookingService::class)->createManualBooking(
        ['hold_key' => $holdKey, 'start_at' => $test->start->toDateString(), 'end_at' => $test->start->addDays(2)->toDateString(), 'vehicle_id' => $test->vehicle->id, 'package_id' => $test->package->id],
        ['email' => 'walkin@example.test', 'full_name' => 'Walk In', 'pickup_type' => 'office', 'business_location_id' => $test->location->id],
        $price,
    );
}

it('queues one "new booking" email to every configured admin address when a customer books', function (): void {
    Mail::fake();

    emailTestBook($this)->assertRedirect();
    $booking = Booking::query()->sole();

    Mail::assertNothingSent(); // never sent inline during the customer's request
    Mail::assertQueued(NewBookingPlacedMail::class, 1);
    Mail::assertQueued(NewBookingPlacedMail::class, fn (NewBookingPlacedMail $mail) => $mail->booking->is($booking)
        && $mail->hasTo('owner@tuktuk.test')
        && $mail->hasTo('desk@tuktuk.test')
        && $mail->hasReplyTo('kenji@example.test'));
});

it('pushes the email onto the queue as a job rather than sending it during the request', function (): void {
    Queue::fake();

    emailTestBook($this)->assertRedirect();

    Queue::assertPushed(SendQueuedMailable::class, fn (SendQueuedMailable $job) => $job->mailable instanceof NewBookingPlacedMail);
});

it('is configured for reliable delivery: after-commit, retried with backoff, bounded runtime', function (): void {
    $booking = emailTestManualBooking($this, (string) Str::uuid());
    $mail = new NewBookingPlacedMail($booking);
    $job = new SendQueuedMailable($mail);

    expect($mail)->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and($job->afterCommit)->toBeTrue()
        ->and($job->tries)->toBe(5)
        ->and($job->timeout)->toBe(60)
        ->and($job->backoff())->toBe([60, 300, 900, 3600]);
});

it('keeps the queued payload tiny — the booking is stored by id, not as a serialized model graph', function (): void {
    $booking = emailTestManualBooking($this, (string) Str::uuid());
    $booking->load(['customer', 'vehicle', 'package', 'addons']);

    $payload = serialize(new SendQueuedMailable(new NewBookingPlacedMail($booking)));

    expect(strlen($payload))->toBeLessThan(6000)
        ->and($payload)->not->toContain('walkin@example.test');
});

it('sends nothing when the booking fails', function (): void {
    Mail::fake();

    // The only tuk tuk is taken between the review page and "Confirm".
    emailTestBook($this, beforeConfirm: fn () => app(BookingService::class)->createHold([
        'hold_key' => (string) Str::uuid(), 'start_at' => $this->start->toDateString(),
        'end_at' => $this->start->addDays(5)->toDateString(), 'vehicle_id' => $this->vehicle->id,
    ]))->assertSessionHasErrors('package_id');

    expect(Booking::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('sends nothing for a booking whose transaction is rolled back', function (): void {
    Mail::fake();

    try {
        DB::transaction(function (): void {
            emailTestManualBooking($this, (string) Str::uuid());
            throw new RuntimeException('Something later in the same transaction failed.');
        });
    } catch (RuntimeException) {
    }

    expect(Booking::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('sends exactly one email when the same booking is confirmed twice (idempotent replay)', function (): void {
    Mail::fake();
    $key = (string) Str::uuid();

    $first = emailTestManualBooking($this, $key);
    $again = emailTestManualBooking($this, $key);

    expect($again->is($first))->toBeTrue();
    Mail::assertQueued(NewBookingPlacedMail::class, 1);
});

it('renders the booking details and a link to the control panel', function (): void {
    emailTestBook($this);
    $booking = Booking::query()->sole();

    $html = (new NewBookingPlacedMail($booking))->render();

    expect($html)->toContain($booking->reference)
        ->toContain('Kenji Sato')
        ->toContain('kenji@example.test')
        ->toContain('Child seat please')
        ->toContain('/control-panel/bookings/'.$booking->id)
        ->and((new NewBookingPlacedMail($booking))->envelope()->subject)->toBe("New booking {$booking->reference} — Kenji Sato");
});

it('does not let customer-typed text inject links or markup into the staff email', function (): void {
    emailTestBook($this, [
        'first_name' => '[Verify payment](https://evil.example/phish)',
        'special_requests' => '<script>alert(1)</script> **urgent** | broken',
    ]);

    $html = (new NewBookingPlacedMail(Booking::query()->sole()))->render();

    expect($html)->not->toContain('href="https://evil.example/phish"')
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<strong>urgent</strong>');
});

it('delivers through a real queue worker run', function (): void {
    // QUEUE_CONNECTION=sync in phpunit.xml executes the job immediately —
    // the same code path a `queue:work` worker runs — into the array mailer.
    emailTestBook($this)->assertRedirect();

    $sent = app('mailer')->getSymfonyTransport()->messages();
    $subjects = $sent->map(fn ($message) => $message->getOriginalMessage()->getSubject());

    // The staff email, plus the customer's own confirmation
    // (CustomerConfirmationEmailTest).
    expect($sent)->toHaveCount(2)
        ->and($subjects->filter(fn ($subject) => str_starts_with($subject, 'New booking MTR-')))->toHaveCount(1);
});

it('still completes the customer\'s booking if the queue backend is down', function (): void {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Queue connection refused'));

    emailTestBook($this)->assertRedirect();

    expect(Booking::query()->count())->toBe(1);
});
