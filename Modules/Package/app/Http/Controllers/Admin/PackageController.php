<?php

declare(strict_types=1);

namespace Modules\Package\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Package\Http\Requests\Admin\SimplePackageForm;
use Modules\Package\Http\Requests\Admin\StorePackageRequest;
use Modules\Package\Http\Requests\Admin\UpdatePackageRequest;
use Modules\Package\Models\Addon;
use Modules\Package\Models\Package;
use Modules\Package\Models\PackageImage;
use Modules\Package\Models\ProductCategory;
use Modules\Package\Services\PackageImageService;
use Modules\Package\Support\PackageCharge;

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
            ->withCount(['pricingTiers', 'pricingTiers as tiers_without_eur_count' => fn ($q) => $q->whereNull('price_eur')])
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
            'vehicles' => Vehicle::query()->active()->tracked()->with('category')->orderBy('plate_no')->orderBy('id')->get(),
            'addons' => Addon::query()->active()->orderBy('sort_order')->get(),
            'offerablePackages' => $this->offerablePackages(),
            'charge' => null,
            'price' => null,
            'priceEur' => null,
            'usesLengthPrices' => false,
        ]);
    }

    public function store(StorePackageRequest $request): RedirectResponse
    {
        $data = $this->packageData($request);
        $data['is_active'] = $request->boolean('is_active', true);

        $package = Package::query()->create($data);
        $package->categories()->sync($request->input('category_ids', []));
        $package->vehicles()->sync($request->input('vehicle_ids', []));
        $this->syncAddons($package, $request);
        $this->syncOffers($package, $request);
        $this->syncPrice($package, $request);

        $this->storePhotos($package, $request);

        return $request->filled('price') || $package->group_pricing
            ? redirect()->route('admin.packages.index')->with('status', __('Package created.'))
            : redirect()->route('admin.packages.edit', $package)->with('status', __('Package created — now add pricing tiers.'));
    }

    public function edit(Package $package): View
    {
        $this->authorize('update', $package);

        $package->load(['pricingTiers', 'seasons', 'images']);
        $offeredAddonIds = $package->addons()->pluck('addons.id')->all();

        return view('package::admin.packages.edit', [
            'package' => $package,
            'productCategories' => ProductCategory::query()->orderBy('sort_order')->orderBy('id')->get(),
            'categories' => VehicleCategory::query()->active()->orderBy('kind')->orderBy('sort_order')->get(),
            'vehicles' => Vehicle::query()->active()->tracked()->with('category')->orderBy('plate_no')->orderBy('id')->get(),
            // A linked add-on that was later hidden stays listed, so saving keeps the link.
            'addons' => Addon::query()->active()->orWhereIn('id', $offeredAddonIds)->orderBy('sort_order')->get(),
            'selectedCategoryIds' => $package->categories()->pluck('vehicle_categories.id')->all(),
            'selectedVehicleIds' => $package->vehicles()->pluck('vehicles.id')->all(),
            'includedAddonIds' => $package->addons()->wherePivot('is_included', true)->pluck('addons.id')->all(),
            'offeredAddonIds' => $offeredAddonIds,
            'offerablePackages' => $this->offerablePackages($package),
            'packageOffers' => $package->offers()->get()->keyBy('offered_package_id'),
            'charge' => PackageCharge::of($package),
            'price' => ! $package->group_pricing && $package->pricingTiers->count() === 1 ? $package->pricingTiers->first()->price : null,
            'priceEur' => ! $package->group_pricing && $package->pricingTiers->count() === 1 ? $package->pricingTiers->first()->price_eur : null,
            'usesLengthPrices' => ! $package->group_pricing && $package->pricingTiers->count() > 1,
        ]);
    }

    public function update(UpdatePackageRequest $request, Package $package): RedirectResponse
    {
        $data = $this->packageData($request);
        $data['is_active'] = $request->boolean('is_active');
        $wasGroupPriced = $package->group_pricing;

        $package->update($data);
        $package->categories()->sync($request->input('category_ids', []));
        $package->vehicles()->sync($request->input('vehicle_ids', []));
        $this->syncAddons($package, $request);
        $this->syncOffers($package, $request);
        $this->syncPrice($package, $request, $wasGroupPriced);

        $this->storePhotos($package, $request);

        return redirect()->route('admin.packages.edit', $package)->with('status', __('Package updated.'));
    }

    /** A new cover replaces the old one as the cover; the old one stays among the photos. */
    private function storePhotos(Package $package, StorePackageRequest|UpdatePackageRequest $request): void
    {
        if ($request->hasFile('cover_image')) {
            $this->images->store($package, $request->file('cover_image'), isPrimary: true);
        }

        foreach ($request->file('images', []) as $file) {
            $this->images->store($package, $file);
        }
    }

    /**
     * The validated form fields as package attributes. `kind` is never
     * taken from the form: the package's category sets it (Package::booted()).
     *
     * @return array<string, mixed>
     */
    private function packageData(StorePackageRequest|UpdatePackageRequest $request): array
    {
        $data = $request->safe()->except([
            'category_ids', 'vehicle_ids', 'images', 'cover_image', 'offered_addon_ids', 'included_addon_ids', 'offers',
            // The simple form's own fields (see SimplePackageForm).
            'charge', 'price', 'price_eur', 'package_days', 'daily_km', 'daily_extra_km_rate', 'daily_extra_km_rate_eur', 'vehicle_type_id', 'group_prices',
        ]);
        // Set by PackageCharge from the form's tick box; false for any way of charging that can't use it.
        $data['group_pricing'] = (bool) ($data['group_pricing'] ?? false);
        $data['deposit_is_percent'] = $request->boolean('deposit_is_percent');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['requires_licence_documents'] = $request->boolean('requires_licence_documents');
        $data['included_km_per_day'] = $request->boolean('included_km_per_day', true);
        // Hidden on the form for hourly / per-person packages.
        $data['min_days'] = $data['min_days'] ?? 1;

        if (($data['pricing_model'] ?? null) !== Package::MODEL_PER_HOUR) {
            $data['min_hours'] = null;
            $data['max_hours'] = null;
        }

        return $data;
    }

    /** An add-on ticked only as "included" still counts as linked. */
    private function syncAddons(Package $package, StorePackageRequest|UpdatePackageRequest $request): void
    {
        $offered = array_map('intval', $request->input('offered_addon_ids', []));
        $included = array_map('intval', $request->input('included_addon_ids', []));

        $package->addons()->sync(
            collect([...$offered, ...$included])->unique()
                ->mapWithKeys(fn (int $id) => [$id => ['is_included' => in_array($id, $included, true)]])
        );
    }

    /**
     * Packages a customer could add to this one: anything booked online.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Package>
     */
    private function offerablePackages(?Package $package = null): \Illuminate\Database\Eloquent\Collection
    {
        return Package::query()
            ->bookable()
            ->when($package !== null, fn ($q) => $q->whereKeyNot($package->id))
            ->with('productCategory')
            ->orderBy('sort_order')
            ->get();
    }

    /** A row with a saving is an offer; an emptied row removes it. */
    private function syncOffers(Package $package, StorePackageRequest|UpdatePackageRequest $request): void
    {
        if (! $request->has('offers')) {
            return;
        }

        $offers = collect($request->validated('offers') ?? [])
            ->filter(fn (array $offer) => filled($offer['discount'] ?? null))
            ->only($this->offerablePackages($package)->modelKeys());

        $package->offers()->whereNotIn('offered_package_id', $offers->keys())->delete();

        foreach ($offers as $offeredPackageId => $offer) {
            $package->offers()->updateOrCreate(
                ['offered_package_id' => $offeredPackageId],
                ['discount' => $offer['discount'], 'discount_eur' => $offer['discount_eur']],
            );
        }
    }

    /**
     * The simple form's price becomes the package's only tier (from 1,
     * no upper limit) — what pricing reads for every way of charging. A
     * package priced by rental length (several tiers) keeps its tiers.
     * A group-priced package's rows replace all its tiers; leaving group
     * pricing replaces those rows with the single price.
     */
    private function syncPrice(Package $package, StorePackageRequest|UpdatePackageRequest $request, bool $wasGroupPriced = false): void
    {
        if ($package->group_pricing) {
            $rows = SimplePackageForm::groupPrices($request);

            if ($rows !== []) {
                $package->pricingTiers()->delete();

                foreach ($rows as $row) {
                    $package->pricingTiers()->create(['min_days' => $row['from'], 'max_days' => $row['to'], 'price' => $row['price'], 'price_eur' => $row['price_eur']]);
                }
            }

            return;
        }

        if (! $request->filled('price') || (! $wasGroupPriced && $package->pricingTiers()->count() > 1)) {
            return;
        }

        $package->pricingTiers()->delete();
        $package->pricingTiers()->create([
            'min_days' => 1,
            'max_days' => null,
            'price' => (float) $request->input('price'),
            'price_eur' => $request->filled('price_eur') ? (float) $request->input('price_eur') : null,
        ]);
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

    public function setCoverImage(Package $package, PackageImage $image): RedirectResponse
    {
        $this->authorize('update', $package);

        abort_unless($image->package_id === $package->id, 404);

        $this->images->setCover($image);

        return redirect()->route('admin.packages.edit', $package)->with('status', __('Cover image changed.'));
    }
}
