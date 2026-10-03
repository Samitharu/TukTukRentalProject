<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Fleet\Http\Requests\Admin\StoreMaintenanceLogRequest;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleMaintenanceLog;

final class VehicleMaintenanceLogController extends Controller
{
    public function store(StoreMaintenanceLogRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->maintenanceLogs()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.fleet.vehicles.edit', $vehicle)->with('status', __('Maintenance log added.'));
    }

    public function destroy(Vehicle $vehicle, VehicleMaintenanceLog $log): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        abort_unless($log->vehicle_id === $vehicle->id, 404);

        $log->delete();

        return redirect()->route('admin.fleet.vehicles.edit', $vehicle)->with('status', __('Maintenance log removed.'));
    }
}
