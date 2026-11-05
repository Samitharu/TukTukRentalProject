<?php

declare(strict_types=1);

namespace Modules\CMS\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Booking\Mail\NewBookingPlacedMail;
use Modules\CMS\Models\ContactSubmission;
use Throwable;

/**
 * Tells staff a visitor used the Contact page, at the same address(es) as new
 * bookings. Queued like NewBookingPlacedMail, so the visitor never waits on
 * SMTP. Replying goes straight to the visitor.
 */
final class ContactMessageReceivedMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public int $timeout = 60;

    public function __construct(public readonly ContactSubmission $contactMessage)
    {
        $this->onQueue(config('booking.notification_queue'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New website message from :name', ['name' => Str::limit(Str::squish($this->contactMessage->name), 80)]),
            replyTo: [$this->contactMessage->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'cms::mail.admin.contact-message',
            with: [
                'adminUrl' => route('admin.messages.show', $this->contactMessage),
                'md' => NewBookingPlacedMail::escapeMarkdown(...),
                'lines' => preg_split('/\R/u', $this->contactMessage->message) ?: [],
            ],
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Contact message email permanently failed.', [
            'contact_submission' => $this->contactMessage->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
