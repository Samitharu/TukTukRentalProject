<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\CMS\Models\Review;

final class ReviewController extends Controller
{
    public function index(): View
    {
        $this->authorize('cms.view');

        // Grows with every completed rental — paginate rather than load all.
        $reviews = Review::query()->orderByDesc('id')->paginate(25);

        return view('cms::admin.reviews.index', compact('reviews'));
    }

    public function toggleApproval(Review $review): RedirectResponse
    {
        $this->authorize('cms.manage');

        $review->update(['is_approved' => ! $review->is_approved]);

        return back()->with('status', __('Review updated.'));
    }

    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('cms.manage');

        $review->delete();

        return redirect()->route('admin.reviews.index')->with('status', __('Review removed.'));
    }
}
