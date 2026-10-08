<?php

declare(strict_types=1);

namespace Modules\Booking\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Booking\Events\BookingPlaced;
use Modules\Booking\Mail\BookingConfirmationMail;
use Modules\Localization\Models\Locale;
use Throwable;

/**
 * Queues the customer's confirmation email, in the language they booked
 * in (customers.locale_preference). Same contract as
 * SendNewBookingNotificationToAdmins: only *queues*, and a queueing
 * failure is logged rather than breaking the already-committed booking.
 */
final class SendBookingConfirmationToCustomer
{
    public function handle(BookingPlaced $event): void
    {
        $customer = $event->booking->customer;

        if ($customer === null || ! filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($customer->email, $customer->full_name)
                ->locale($this->localeFor($customer->locale_preference))
                ->queue(new BookingConfirmationMail($event->booking));
        } catch (Throwable $exception) {
            Log::error('Could not queue the booking confirmation email to the customer.', [
                'booking' => $event->booking->reference,
                'exception' => $exception,
            ]);
        }
    }

    /** The customer's language if the site still offers it, else the default. */
    private function localeFor(?string $preference): string
    {
        $active = Locale::query()->where('is_active', true)->pluck('code');

        if ($preference !== null && $active->contains($preference)) {
            return $preference;
        }

        return Locale::query()->where('is_default', true)->value('code') ?? config('app.locale');
    }
}
