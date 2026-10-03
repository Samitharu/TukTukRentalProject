<?php

declare(strict_types=1);

namespace Modules\Availability\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Availability\Http\Requests\Admin\StoreAvailabilityBlackoutRequest;
use Modules\Availability\Models\AvailabilityBlackout;
use Modules\Fleet\Models\Vehicle;

final class AvailabilityBlackoutController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', AvailabilityBlackout::class);

        $blackouts = AvailabilityBlackout::query()->with('vehicle')->orderByDesc('starts_on')->get();
        $vehicles = Vehicle::query()->orderBy('plate_no')->get();

        return view('availability::admin.blackouts.index', compact('blackouts', 'vehicles'));
    }

    public function store(StoreAvailabilityBlackoutRequest $request): RedirectResponse
    {
        AvailabilityBlackout::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.blackouts.index')->with('status', __('Blackout added.'));
    }

    public function destroy(AvailabilityBlackout $blackout): RedirectResponse
    {
        $this->authorize('delete', $blackout);

        $blackout->delete();

        return redirect()->route('admin.blackouts.index')->with('status', __('Blackout removed.'));
    }
}
