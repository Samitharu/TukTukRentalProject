<?php

declare(strict_types=1);

namespace Modules\Package\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Package\Http\Requests\Admin\StorePackageRequest;
use Modules\Package\Http\Requests\Admin\UpdatePackageRequest;
use Modules\Package\Models\Addon;
use Modules\Package\Models\Package;
use Modules\Package\Models\PackageImage;
use Modules\Package\Services\PackageImageService;

final class PackageController extends Controller
{
    public function __construct(private readonly PackageImageService $images)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Package::class);

        $packages = Package::query()->withCount('pricingTiers')->orderBy('sort_order')->get();

        return view('package::admin.packages.index', compact('packages'));
    }

    public function create(): View
    {
        $this->authorize('create', Package::class);

        return view('package::admin.packages.create', [
            'categories' => VehicleCategory::query()->active()->orderBy('kind')->orderBy('sort_order')->get(),
            'vehicles' => Vehicle::query()->active()->with('category')->orderBy('plate_no')->orderBy('id')->get(),
        ]);
    }

    public function store(StorePackageRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['category_ids', 'vehicle_ids', 'images']);
        $data['kind'] = $data['kind'] ?? VehicleCategory::KIND_VEHICLE;
        $data['deposit_is_percent'] = $request->boolean('deposit_is_percent');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_featured'] = $request->boolean('is_featured');

        $package = Package::query()->create($data);
        $package->categories()->sync($request->input('category_ids', []));
        $package->vehicles()->sync($request->input('vehicle_ids', []));

        foreach ($request->file('images', []) as $index => $file) {
            $this->images->store($package, $file, isPrimary: $index === 0);
        }

        return redirect()->route('admin.packages.edit', $package)->with('status', __('Package created — now add pricing tiers.'));
    }

    public function edit(Package $package): View
    {
        $this->authorize('update', $package);

        $package->load(['pricingTiers', 'seasons', 'addons', 'images']);

        return view('package::admin.packages.edit', [
            'package' => $package,
            'categories' => VehicleCategory::query()->active()->orderBy('kind')->orderBy('sort_order')->get(),
            'vehicles' => Vehicle::query()->active()->with('category')->orderBy('plate_no')->orderBy('id')->get(),
            'addons' => Addon::query()->active()->orderBy('sort_order')->get(),
            'selectedCategoryIds' => $package->categories()->pluck('vehicle_categories.id')->all(),
            'selectedVehicleIds' => $package->vehicles()->pluck('vehicles.id')->all(),
            'includedAddonIds' => $package->addons()->wherePivot('is_included', true)->pluck('addons.id')->all(),
            'offeredAddonIds' => $package->addons()->pluck('addons.id')->all(),
        ]);
    }

    public function update(UpdatePackageRequest $request, Package $package): RedirectResponse
    {
        $data = $request->safe()->except(['category_ids', 'vehicle_ids', 'images']);
        $data['deposit_is_percent'] = $request->boolean('deposit_is_percent');
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');

        $package->update($data);
        $package->categories()->sync($request->input('category_ids', []));
        $package->vehicles()->sync($request->input('vehicle_ids', []));

        foreach ($request->file('images', []) as $file) {
            $this->images->store($package, $file);
        }

        return redirect()->route('admin.packages.edit', $package)->with('status', __('Package updated.'));
    }

    public function destroy(Package $package): RedirectResponse
    {
        $this->authorize('delete', $package);

        $package->delete();

        return redirect()->route('admin.packages.index')->with('status', __('Package removed.'));
    }

    public function destroyImage(Package $package, PackageImage $image): RedirectResponse
    {
        $this->authorize('update', $package);

        abort_unless($image->package_id === $package->id, 404);

        $this->images->delete($image);

        return redirect()->route('admin.packages.edit', $package)->with('status', __('Image removed.'));
    }
}
