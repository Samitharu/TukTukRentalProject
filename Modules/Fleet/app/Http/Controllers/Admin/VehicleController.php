<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Fleet\Http\Requests\Admin\StoreVehicleRequest;
use Modules\Fleet\Http\Requests\Admin\UpdateVehicleRequest;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Fleet\Models\VehicleImage;
use Modules\Fleet\Services\VehicleImageService;

final class VehicleController extends Controller
{
    public function __construct(private readonly VehicleImageService $images)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Vehicle::class);

        $vehicles = Vehicle::query()->with(['category', 'images'])->orderBy('plate_no')->paginate(20);

        return view('fleet::admin.vehicles.index', compact('vehicles'));
    }

    public function create(): View
    {
        $this->authorize('create', Vehicle::class);

        $categories = VehicleCategory::query()->active()->orderBy('sort_order')->get();

        return view('fleet::admin.vehicles.create', compact('categories'));
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('images');

        $vehicle = Vehicle::query()->create($data);

        foreach ($request->file('images', []) as $index => $file) {
            $this->images->store($vehicle, $file, isPrimary: $index === 0);
        }

        return redirect()->route('admin.fleet.vehicles.index')->with('status', __('Vehicle added.'));
    }

    public function edit(Vehicle $vehicle): View
    {
        $this->authorize('update', $vehicle);

        $categories = VehicleCategory::query()->active()->orderBy('sort_order')->get();
        $vehicle->load('images');

        return view('fleet::admin.vehicles.edit', compact('vehicle', 'categories'));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->safe()->except('images');

        $vehicle->update($data);

        foreach ($request->file('images', []) as $file) {
            $this->images->store($vehicle, $file);
        }

        return redirect()->route('admin.fleet.vehicles.index')->with('status', __('Vehicle updated.'));
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('delete', $vehicle);

        $vehicle->delete();

        return redirect()->route('admin.fleet.vehicles.index')->with('status', __('Vehicle removed.'));
    }

    public function destroyImage(Vehicle $vehicle, VehicleImage $image): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        abort_unless($image->vehicle_id === $vehicle->id, 404);

        $this->images->delete($image);

        return redirect()->route('admin.fleet.vehicles.edit', $vehicle)->with('status', __('Image removed.'));
    }
}
