<?php

declare(strict_types=1);

namespace Modules\Package\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Package\Http\Requests\Admin\StorePackageRequest;
use Modules\Package\Http\Requests\Admin\UpdatePackageRequest;
use Modules\Package\Models\Addon;
use Modules\Package\Models\Package;
use Modules\Package\Models\PackageImage;
use Modules\Package\Models\ProductCategory;
use Modules\Package\Services\PackageImageService;

final class PackageController extends Controller
{
    public function __construct(private readonly PackageImageService $images)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Package::class);

        $packages = Package::query()
            ->with('productCategory')
            ->withCount('pricingTiers')
            ->when($request->filled('category'), fn ($q) => $q->where('product_category_id', $request->integer('category')))
            ->orderBy('sort_order')
            ->get();

        return view('package::admin.packages.index', [
            'packages' => $packages,
            'productCategories' => ProductCategory::query()->orderBy('sort_order')->orderBy('id')->get(),
            'selectedCategoryId' => $request->integer('category') ?: null,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Package::class);

        return view('package::admin.packages.create', [
            'productCategories' => ProductCategory::query()->orderBy('sort_order')->orderBy('id')->get(),
            'preselectedCategoryId' => $request->integer('category') ?: null,
            'categories' => VehicleCategory::query()->active()->orderBy('kind')->orderBy('sort_order')->get(),
            'vehicles' => Vehicle::query()->active()->with('category')->orderBy('plate_no')->orderBy('id')->get(),
        ]);
    }

    public function store(StorePackageRequest $request): RedirectResponse
    {
        $data = $this->packageData($request);
        $data['is_active'] = $request->boolean('is_active', true);

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
            'productCategories' => ProductCategory::query()->orderBy('sort_order')->orderBy('id')->get(),
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
        $data = $this->packageData($request);
        $data['is_active'] = $request->boolean('is_active');

        $package->update($data);
        $package->categories()->sync($request->input('category_ids', []));
        $package->vehicles()->sync($request->input('vehicle_ids', []));

        foreach ($request->file('images', []) as $file) {
            $this->images->store($package, $file);
        }

        return redirect()->route('admin.packages.edit', $package)->with('status', __('Package updated.'));
    }

    /**
     * The validated form fields as package attributes. `kind` is never
     * taken from the form: the package's category sets it (Package::booted()).
     *
     * @return array<string, mixed>
     */
    private function packageData(StorePackageRequest|UpdatePackageRequest $request): array
    {
        $data = $request->safe()->except(['category_ids', 'vehicle_ids', 'images']);
        $data['deposit_is_percent'] = $request->boolean('deposit_is_percent');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['included_km_per_day'] = $request->boolean('included_km_per_day', true);
        // Hidden on the form for hourly / per-person packages.
        $data['min_days'] = $data['min_days'] ?? 1;

        if (($data['pricing_model'] ?? null) !== Package::MODEL_PER_HOUR) {
            $data['min_hours'] = null;
            $data['max_hours'] = null;
        }

        return $data;
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
