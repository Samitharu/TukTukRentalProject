<?php

declare(strict_types=1);

namespace Modules\Availability\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Availability\Http\Requests\Admin\StoreBusinessLocationRequest;
use Modules\Availability\Http\Requests\Admin\UpdateBusinessLocationRequest;
use Modules\Availability\Models\BusinessLocation;
use Modules\Core\Support\GoogleMapsLink;

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
        [$data, $locationFound] = GoogleMapsLink::fillCoordinates($request->validated());
        $data['is_pickup_point'] = $request->boolean('is_pickup_point', true);
        $data['is_active'] = $request->boolean('is_active', true);

        BusinessLocation::query()->create($data);

        return redirect()->route('admin.locations.index')->with('status', $this->savedMessage(__('Location added.'), $locationFound));
    }

    public function edit(BusinessLocation $location): View
    {
        $this->authorize('update', $location);

        return view('availability::admin.locations.edit', compact('location'));
    }

    public function update(UpdateBusinessLocationRequest $request, BusinessLocation $location): RedirectResponse
    {
        [$data, $locationFound] = GoogleMapsLink::fillCoordinates($request->validated(), $location);
        $data['is_pickup_point'] = $request->boolean('is_pickup_point');
        $data['is_active'] = $request->boolean('is_active');

        $location->update($data);

        return redirect()->route('admin.locations.index')->with('status', $this->savedMessage(__('Location updated.'), $locationFound));
    }

    public function destroy(BusinessLocation $location): RedirectResponse
    {
        $this->authorize('delete', $location);

        $location->delete();

        return redirect()->route('admin.locations.index')->with('status', __('Location removed.'));
    }

    private function savedMessage(string $message, bool $locationFound): string
    {
        return $locationFound
            ? $message
            : $message.' '.__('We could not read the exact spot from that Google Maps link — open the location and drop the pin on the map so customers see it.');
    }
}
