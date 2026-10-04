<?php

declare(strict_types=1);

namespace Modules\Booking\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Booking\Events\BookingPlaced;
use Modules\Booking\Mail\NewBookingPlacedMail;
use Throwable;

/**
 * Runs synchronously but only *queues* the email — the customer's
 * checkout request never waits on SMTP, and a mail-server outage can never
 * fail a booking: the queued job retries on its own (see
 * NewBookingPlacedMail::$tries / $backoff).
 */
final class SendNewBookingNotificationToAdmins
{
    public function handle(BookingPlaced $event): void
    {
        $recipients = config('booking.admin_notification_emails', []);

        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->queue(new NewBookingPlacedMail($event->booking));
        } catch (Throwable $exception) {
            // The booking is already committed; failing to *enqueue* (e.g.
            // the queue backend is down) must not turn the customer's
            // success page into an error. Log loudly instead.
            Log::error('Could not queue the new-booking admin email.', [
                'booking' => $event->booking->reference,
                'exception' => $exception,
            ]);
        }
    }
}
