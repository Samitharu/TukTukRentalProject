<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Core\Support\GoogleMapsLink;
use Modules\Fleet\Http\Requests\Admin\StoreVehicleRequest;
use Modules\Fleet\Http\Requests\Admin\UnitRules;
use Modules\Fleet\Http\Requests\Admin\UpdateVehicleRequest;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Fleet\Models\VehicleImage;
use Modules\Fleet\Services\VehicleImageService;

/**
 * Admin CRUD for every bookable unit — tuk tuks and stays (cabanas,
 * rooms) alike; the category's kind decides which fields apply.
 */
final class VehicleController extends Controller
{
    /** Fields that only describe a vehicle; always null on a stay. */
    private const array VEHICLE_ONLY_FIELDS = ['plate_no', 'model', 'year', 'colour', 'transmission', 'fuel_type'];

    public function __construct(private readonly VehicleImageService $images)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Vehicle::class);

        $vehicles = Vehicle::query()
            ->with(['category', 'images'])
            ->orderBy(VehicleCategory::query()->select('kind')->whereColumn('vehicle_categories.id', 'vehicles.category_id'))
            ->orderBy('plate_no')
            ->orderBy('id')
            ->paginate(20);

        return view('fleet::admin.vehicles.index', compact('vehicles'));
    }

    public function create(): View
    {
        $this->authorize('create', Vehicle::class);

        return view('fleet::admin.vehicles.create', [
            'categories' => VehicleCategory::query()->active()->orderBy('sort_order')->get(),
            'features' => UnitRules::FEATURES,
        ]);
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        [$data, $locationFound] = $this->prepare($request->safe()->except('images'));

        $vehicle = Vehicle::query()->create($data);

        foreach ($request->file('images', []) as $index => $file) {
            $this->images->store($vehicle, $file, isPrimary: $index === 0);
        }

        return redirect()->route('admin.fleet.vehicles.index')->with('status', $this->savedMessage(__('Unit added.'), $locationFound));
    }

    public function edit(Vehicle $vehicle): View
    {
        $this->authorize('update', $vehicle);

        $vehicle->load('images');

        return view('fleet::admin.vehicles.edit', [
            'vehicle' => $vehicle,
            'categories' => VehicleCategory::query()->active()->orderBy('sort_order')->get(),
            'features' => UnitRules::FEATURES,
        ]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        [$data, $locationFound] = $this->prepare($request->safe()->except('images'), $vehicle);

        $vehicle->update($data);

        foreach ($request->file('images', []) as $file) {
            $this->images->store($vehicle, $file);
        }

        return redirect()->route('admin.fleet.vehicles.index')->with('status', $this->savedMessage(__('Unit updated.'), $locationFound));
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('delete', $vehicle);

        $vehicle->delete();

        return redirect()->route('admin.fleet.vehicles.index')->with('status', __('Unit removed.'));
    }

    public function destroyImage(Vehicle $vehicle, VehicleImage $image): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        abort_unless($image->vehicle_id === $vehicle->id, 404);

        $this->images->delete($image);

        return redirect()->route('admin.fleet.vehicles.edit', $vehicle)->with('status', __('Image removed.'));
    }

    /**
     * Blanks vehicle-only fields for stays and fills the pin from the
     * Google Maps link when the form didn't supply one — or supplied the
     * old pin alongside a new link (a short link the browser couldn't
     * read, so the map still showed the previous place).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: bool}  data, and whether a pasted link without coordinates could be read
     */
    private function prepare(array $data, ?Vehicle $existing = null): array
    {
        $isStay = UnitRules::isStayCategory($data['category_id'] ?? null);

        if ($isStay) {
            $data = [...$data, ...array_fill_keys(self::VEHICLE_ONLY_FIELDS, null)];
        }

        $data['features'] = array_values(array_intersect(
            $data['features'] ?? [],
            UnitRules::FEATURES[$isStay ? VehicleCategory::KIND_STAY : VehicleCategory::KIND_VEHICLE],
        ));
        $url = $data['google_maps_url'] ?? null;

        if (! is_string($url) || $url === '') {
            return [$data, true];
        }

        $hasPin = ($data['lat'] ?? null) !== null;
        $stalePin = $existing !== null
            && $hasPin
            && $existing->google_maps_url !== $url
            && (float) $data['lat'] === (float) $existing->lat
            && (float) ($data['lng'] ?? 0) === (float) $existing->lng;

        if ($hasPin && ! $stalePin) {
            return [$data, true];
        }

        $coordinates = GoogleMapsLink::resolveCoordinates($url);

        return $coordinates !== null
            ? [[...$data, ...$coordinates], true]
            : [$data, false];
    }

    private function savedMessage(string $message, bool $locationFound): string
    {
        return $locationFound
            ? $message
            : $message.' '.__('We could not read the exact spot from that Google Maps link — open the unit and drop the pin on the map so customers see it.');
    }
}
