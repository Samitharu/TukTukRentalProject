<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\CMS\Http\Requests\Front\ContactRequest;
use Modules\CMS\Mail\ContactMessageReceivedMail;
use Modules\CMS\Models\ContactSubmission;
use Throwable;

final class ContactController extends Controller
{
    public function show(): View
    {
        return view('cms::front.contact');
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $contactMessage = ContactSubmission::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'subject' => $request->string('subject')->toString() ?: null,
            'message' => $request->string('message')->toString(),
            'locale' => app()->getLocale(),
            'ip' => $request->ip(),
        ]);

        $this->notifyStaff($contactMessage);

        return back()->with('status', __('core::front.contact_thank_you'));
    }

    private function notifyStaff(ContactSubmission $contactMessage): void
    {
        $recipients = config('booking.admin_notification_emails', []);

        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->queue(new ContactMessageReceivedMail($contactMessage));
        } catch (Throwable $exception) {
            // The message is saved and waiting in the control panel, so a
            // queue outage must not show the visitor an error.
            Log::error('Could not queue the contact message email.', [
                'contact_submission' => $contactMessage->id,
                'exception' => $exception,
            ]);
        }
    }
}
