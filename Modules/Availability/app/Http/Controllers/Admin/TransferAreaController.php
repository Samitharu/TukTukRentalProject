<?php

declare(strict_types=1);

namespace Modules\Availability\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Availability\Http\Requests\Admin\TransferAreaRequest;
use Modules\Availability\Models\TransferArea;

/**
 * Airport pickup destinations: each area's trip price and how long the
 * vehicle is away for one trip (the time slot a booking reserves).
 */
final class TransferAreaController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', TransferArea::class);

        return view('availability::admin.transfer-areas.index', [
            'areas' => TransferArea::query()->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', TransferArea::class);

        return view('availability::admin.transfer-areas.create');
    }

    public function store(TransferAreaRequest $request): RedirectResponse
    {
        TransferArea::query()->create([...$request->validated(), 'is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('admin.transfer-areas.index')->with('status', __('Area added.'));
    }

    public function edit(TransferArea $area): View
    {
        $this->authorize('update', $area);

        return view('availability::admin.transfer-areas.edit', compact('area'));
    }

    public function update(TransferAreaRequest $request, TransferArea $area): RedirectResponse
    {
        $area->update([...$request->validated(), 'is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.transfer-areas.index')->with('status', __('Area updated.'));
    }

    public function destroy(TransferArea $area): RedirectResponse
    {
        $this->authorize('delete', $area);

        // Bookings keep their price snapshot; their area link is nulled.
        $area->delete();

        return redirect()->route('admin.transfer-areas.index')->with('status', __('Area removed.'));
    }
}
