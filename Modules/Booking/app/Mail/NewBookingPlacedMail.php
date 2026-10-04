<?php

declare(strict_types=1);

namespace Modules\Booking\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\Booking;
use Throwable;

/**
 * "New booking placed" email to the staff address(es) in
 * config('booking.admin_notification_emails').
 *
 * Queued, never sent inline: the checkout request only pushes a small job
 * (SerializesModels stores just the booking's id, not the model graph —
 * the worker re-fetches it, so the payload stays tiny no matter how much
 * is loaded at dispatch time). ShouldQueueAfterCommit means it is pushed
 * only once the surrounding booking transaction has committed.
 *
 * Requires a running worker: `php artisan queue:work` (QUEUE_CONNECTION is
 * `database` in .env, so jobs wait in the `jobs` table until one runs).
 */
final class NewBookingPlacedMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable;
    use SerializesModels;

    /** Attempts before the job lands in failed_jobs (SMTP hiccups are usually transient). */
    public int $tries = 5;

    /** Seconds between attempts: 1 min, 5 min, 15 min, 1 h. */
    public array $backoff = [60, 300, 900, 3600];

    /** Rendering + one SMTP round-trip; a hung connection must not block the worker. */
    public int $timeout = 60;

    public function __construct(public readonly Booking $booking)
    {
        $this->onQueue(config('booking.notification_queue'));
    }

    public function envelope(): Envelope
    {
        $this->booking->loadMissing('customer');

        return new Envelope(
            subject: __('New booking :reference — :customer', [
                'reference' => $this->booking->reference,
                'customer' => $this->booking->customer?->full_name ?? '—',
            ]),
            replyTo: $this->booking->customer?->email ? [$this->booking->customer->email] : [],
        );
    }

    public function content(): Content
    {
        $this->booking->loadMissing(['customer', 'vehicle.category', 'package', 'businessLocation', 'deliveryZone', 'addons.addon']);

        return new Content(
            markdown: 'booking::mail.admin.new-booking',
            with: [
                'booking' => $this->booking,
                // Rental days, or nights for a stay.
                'days' => $this->booking->lengthInDays(),
                'adminUrl' => route('admin.bookings.show', $this->booking),
                'md' => self::escapeMarkdown(...),
            ],
        );
    }

    /**
     * For customer-typed values (name, phone, special requests) placed in
     * the Markdown template. Blade's {{ }} already HTML-escapes them, but
     * Markdown syntax survives that: a "name" like `[Pay here](https://…)`
     * would become a clickable link in the staff inbox, and a `|` would
     * break the details table. Backslash-escapes Markdown punctuation and
     * flattens line breaks. (`<`, `>`, `&` are left to Blade's entities.)
     */
    public static function escapeMarkdown(?string $value): string
    {
        $flat = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|~])/', '\\\\$1', $flat);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('New-booking admin email permanently failed.', [
            'booking' => $this->booking->reference,
            'exception' => $exception->getMessage(),
        ]);
    }
}
