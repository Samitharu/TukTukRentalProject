<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\CMS\Http\Requests\Front\ContactRequest;
use Modules\CMS\Models\ContactSubmission;

final class ContactController extends Controller
{
    public function show(): View
    {
        return view('cms::front.contact');
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        ContactSubmission::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'subject' => $request->string('subject')->toString() ?: null,
            'message' => $request->string('message')->toString(),
            'locale' => app()->getLocale(),
            'ip' => $request->ip(),
        ]);

        return back()->with('status', __('core::front.contact_thank_you'));
    }
}
