<?php

declare(strict_types=1);

namespace Modules\Availability\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Availability\Http\Requests\Admin\StoreBusinessLocationRequest;
use Modules\Availability\Http\Requests\Admin\UpdateBusinessLocationRequest;
use Modules\Availability\Models\BusinessLocation;

final class BusinessLocationController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', BusinessLocation::class);

        $locations = BusinessLocation::query()->orderBy('id')->get();

        return view('availability::admin.locations.index', compact('locations'));
    }

    public function create(): View
    {
        $this->authorize('create', BusinessLocation::class);

        return view('availability::admin.locations.create');
    }

    public function store(StoreBusinessLocationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_pickup_point'] = $request->boolean('is_pickup_point', true);
        $data['is_active'] = $request->boolean('is_active', true);

        BusinessLocation::query()->create($data);

        return redirect()->route('admin.locations.index')->with('status', __('Location added.'));
    }

    public function edit(BusinessLocation $location): View
    {
        $this->authorize('update', $location);

        return view('availability::admin.locations.edit', compact('location'));
    }

    public function update(UpdateBusinessLocationRequest $request, BusinessLocation $location): RedirectResponse
    {
        $data = $request->validated();
        $data['is_pickup_point'] = $request->boolean('is_pickup_point');
        $data['is_active'] = $request->boolean('is_active');

        $location->update($data);

        return redirect()->route('admin.locations.index')->with('status', __('Location updated.'));
    }

    public function destroy(BusinessLocation $location): RedirectResponse
    {
        $this->authorize('delete', $location);

        $location->delete();

        return redirect()->route('admin.locations.index')->with('status', __('Location removed.'));
    }
}
