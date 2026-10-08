<?php

declare(strict_types=1);

namespace Modules\Booking\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\Booking;
use Throwable;

/**
 * "Your booking is confirmed" email to the customer — the one the
 * confirmation page promises ("A confirmation email is on its way").
 *
 * Queued after commit for the same reasons as NewBookingPlacedMail, and
 * rendered in the customer's language: the listener sets ->locale(), which
 * switches the app locale while this is rendered on the worker, so every
 * __() and the links below come out in that language.
 */
final class BookingConfirmationMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public int $timeout = 60;

    public function __construct(public readonly Booking $booking)
    {
        $this->onQueue(config('booking.notification_queue'));
    }

    public function envelope(): Envelope
    {
        $businessEmail = config('core.business.email');

        return new Envelope(
            subject: __('core::front.email_confirm_subject', ['reference' => $this->booking->reference]),
            // Replies go to the business inbox, not the no-reply sender.
            replyTo: filled($businessEmail) ? [new Address($businessEmail, config('app.name'))] : [],
        );
    }

    public function content(): Content
    {
        $this->booking->loadMissing(['customer', 'vehicle.category', 'package', 'businessLocation', 'deliveryZone', 'addons.addon']);

        $routeParams = ['locale' => $this->locale ?? app()->getLocale(), 'reference' => $this->booking->reference];

        return new Content(
            markdown: 'booking::mail.customer.confirmation',
            with: [
                'booking' => $this->booking,
                // Rental days, or nights for a stay.
                'days' => $this->booking->lengthInDays(),
                'bookingUrl' => route('booking.confirmation', $routeParams),
                'receiptUrl' => route('booking.receipt', $routeParams),
                'md' => NewBookingPlacedMail::escapeMarkdown(...),
            ],
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Booking confirmation email to customer permanently failed.', [
            'booking' => $this->booking->reference,
            'exception' => $exception->getMessage(),
        ]);
    }
}
