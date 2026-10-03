<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\CMS\Http\Requests\Admin\StoreTestimonialRequest;
use Modules\CMS\Models\Testimonial;

final class TestimonialController extends Controller
{
    public function index(): View
    {
        $this->authorize('cms.view');

        $testimonials = Testimonial::query()->orderByDesc('id')->get();

        return view('cms::admin.testimonials.index', compact('testimonials'));
    }

    public function create(): View
    {
        $this->authorize('cms.manage');

        return view('cms::admin.testimonials.create');
    }

    public function store(StoreTestimonialRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_approved'] = $request->boolean('is_approved', true);

        Testimonial::query()->create($data);

        return redirect()->route('admin.testimonials.index')->with('status', __('Testimonial added.'));
    }

    public function toggleApproval(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('cms.manage');

        $testimonial->update(['is_approved' => ! $testimonial->is_approved]);

        return back()->with('status', __('Testimonial updated.'));
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('cms.manage');

        $testimonial->delete();

        return redirect()->route('admin.testimonials.index')->with('status', __('Testimonial removed.'));
    }
}
