<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\CMS\Models\ContactSubmission;

/** What visitors send through the website's Contact page. */
final class ContactMessageController extends Controller
{
    public function index(): View
    {
        $this->authorize('cms.view');

        $contactMessages = ContactSubmission::query()->orderByDesc('id')->paginate(25);

        return view('cms::admin.messages.index', compact('contactMessages'));
    }

    public function show(ContactSubmission $contactMessage): View
    {
        $this->authorize('cms.view');

        if (! $contactMessage->is_read) {
            $contactMessage->update(['is_read' => true]);
        }

        return view('cms::admin.messages.show', compact('contactMessage'));
    }

    public function markUnread(ContactSubmission $contactMessage): RedirectResponse
    {
        $this->authorize('cms.manage');

        $contactMessage->update(['is_read' => false]);

        return redirect()->route('admin.messages.index')->with('status', __('Message marked as unread.'));
    }

    public function destroy(ContactSubmission $contactMessage): RedirectResponse
    {
        $this->authorize('cms.manage');

        $contactMessage->delete();

        return redirect()->route('admin.messages.index')->with('status', __('Message removed.'));
    }
}
