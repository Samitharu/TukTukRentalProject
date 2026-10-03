<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\CMS\Http\Requests\Admin\StoreFaqRequest;
use Modules\CMS\Models\Faq;

final class FaqController extends Controller
{
    public function index(): View
    {
        $this->authorize('cms.view');

        $faqs = Faq::query()->orderBy('sort_order')->get();

        return view('cms::admin.faqs.index', compact('faqs'));
    }

    public function create(): View
    {
        $this->authorize('cms.manage');

        return view('cms::admin.faqs.create');
    }

    public function store(StoreFaqRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        Faq::query()->create($data);

        return redirect()->route('admin.faqs.index')->with('status', __('FAQ added.'));
    }

    public function edit(Faq $faq): View
    {
        $this->authorize('cms.manage');

        return view('cms::admin.faqs.edit', compact('faq'));
    }

    public function update(StoreFaqRequest $request, Faq $faq): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $faq->update($data);

        return redirect()->route('admin.faqs.index')->with('status', __('FAQ updated.'));
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $this->authorize('cms.manage');

        $faq->delete();

        return redirect()->route('admin.faqs.index')->with('status', __('FAQ removed.'));
    }
}
