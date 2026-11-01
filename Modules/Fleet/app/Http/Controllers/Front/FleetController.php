<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Support\Seo;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Package\Models\ProductCategory;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public listing/detail pages for both kinds of unit: tuk tuks under
 * /tuk-tuks, cabanas and rooms under /stays. A unit's slug opened under
 * the other kind's URL redirects to its canonical page.
 */
final class FleetController extends Controller
{
    /** Tuk tuks: rental units of the first rental line (and any not yet in a line). */
    public function index(Request $request): View
    {
        return view('fleet::front.index', [
            'vehicles' => $this->activeUnits(VehicleCategory::KIND_VEHICLE, $request, tukTukLine: true),
            'categories' => $this->rentalUnitTypes(tukTukLine: true),
            'listRoute' => 'fleet.index',
            'title' => __('core::front.fleet_title'),
            'intro' => __('core::front.fleet_intro'),
            'bookLine' => $this->tukTukLineId(),
        ]);
    }

    /** Vehicle Rent: rental units of every other rental line (scooters, cars…). */
    public function vehicles(Request $request): View
    {
        $line = ProductCategory::query()->active()->where('kind', VehicleCategory::KIND_VEHICLE)
            ->whereKeyNot($this->tukTukLineId() ?? 0)->orderBy('sort_order')->first();

        return view('fleet::front.index', [
            'vehicles' => $this->activeUnits(VehicleCategory::KIND_VEHICLE, $request, tukTukLine: false),
            'categories' => $this->rentalUnitTypes(tukTukLine: false),
            'listRoute' => 'vehicles.index',
            'title' => $line?->name ?? __('core::front.nav_vehicles'),
            'intro' => __('core::front.vehicles_intro'),
            'bookLine' => $line?->id,
        ]);
    }

    public function stays(Request $request): View
    {
        return view('fleet::front.stays', [
            'units' => $this->activeUnits(VehicleCategory::KIND_STAY, $request),
            'categories' => VehicleCategory::query()->active()->ofKind(VehicleCategory::KIND_STAY)->orderBy('sort_order')->get(),
        ]);
    }

    public function show(string $locale, string $slug): View|RedirectResponse
    {
        return $this->showUnit($locale, $slug, VehicleCategory::KIND_VEHICLE);
    }

    public function showStay(string $locale, string $slug): View|RedirectResponse
    {
        return $this->showUnit($locale, $slug, VehicleCategory::KIND_STAY);
    }

    private function showUnit(string $locale, string $slug, string $kind): View|RedirectResponse
    {
        $vehicle = Vehicle::findBySlug($locale, $slug);

        if ($vehicle === null || $vehicle->status !== Vehicle::STATUS_ACTIVE) {
            throw new NotFoundHttpException();
        }

        $vehicle->load('images', 'category', 'routeSlugs');

        // Airport vehicles are booked as a trip, never shown as a unit.
        if ($vehicle->isTransfer()) {
            throw new NotFoundHttpException();
        }

        if ($vehicle->kind() !== $kind) {
            return redirect()->route($vehicle->isStay() ? 'stays.show' : 'fleet.show', $slug, 301);
        }

        $isStay = $vehicle->isStay();
        $image = Seo::storageImage($vehicle->primaryImage()?->path);

        return view('fleet::front.show', [
            'vehicle' => $vehicle,
            'image' => $image,
            'alternates' => Seo::alternatesForModel($vehicle, $isStay ? 'stays.show' : 'fleet.show'),
            'schema' => array_filter([
                $isStay ? $this->accommodationSchema($vehicle, $image) : null,
                Seo::breadcrumbs([
                    [__('core::front.nav_home'), route('home')],
                    [__($isStay ? 'core::front.nav_stays' : 'core::front.nav_fleet'), route($isStay ? 'stays.index' : 'fleet.index')],
                    [(string) $vehicle->name, url()->current()],
                ]),
            ]),
        ]);
    }

    /**
     * schema.org Accommodation for a cabana/room: where it is (address +
     * map pin), how many guests it sleeps, and its amenities.
     *
     * @return array<string, mixed>
     */
    private function accommodationSchema(Vehicle $unit, ?string $image): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Accommodation',
            'name' => (string) $unit->name,
            'description' => Seo::description((string) $unit->description) ?: null,
            'url' => url()->current(),
            'image' => $unit->images->map(fn ($photo) => Seo::storageImage($photo->path))->filter()->values()->all() ?: $image,
            'occupancy' => ['@type' => 'QuantitativeValue', 'maxValue' => $unit->seats],
            'address' => $unit->address ? ['@type' => 'PostalAddress', 'streetAddress' => $unit->address, 'addressCountry' => 'LK'] : null,
            'geo' => $unit->hasCoordinates() ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $unit->lat, 'longitude' => (float) $unit->lng] : null,
            'hasMap' => $unit->mapUrl(),
            'amenityFeature' => array_map(
                fn (string $feature) => ['@type' => 'LocationFeatureSpecification', 'name' => ucfirst(str_replace('_', ' ', $feature)), 'value' => true],
                $unit->features ?? [],
            ) ?: null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return Collection<int, Vehicle>
     */
    private function activeUnits(string $kind, Request $request, ?bool $tukTukLine = null): Collection
    {
        return Vehicle::query()
            ->active()
            ->ofKind($kind)
            ->when($tukTukLine !== null, fn ($q) => $q->whereIn('category_id', $this->rentalUnitTypesQuery($tukTukLine)->select('id')))
            // routeSlugs: the card links call slugFor() — one query per
            // vehicle without this (N+1).
            ->with(['images', 'category', 'routeSlugs' => fn ($q) => $q->where('locale', app()->getLocale())])
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->orderBy('plate_no')
            ->orderBy('id')
            ->get();
    }

    /** The Tuk Tuk line: the first rental line on the site. */
    private function tukTukLineId(): ?int
    {
        return once(fn () => ProductCategory::query()->where('kind', VehicleCategory::KIND_VEHICLE)->orderBy('sort_order')->orderBy('id')->value('id'));
    }

    /**
     * Rental unit types of the Tuk Tuk line (plus any in no line yet), or
     * of every other rental line.
     *
     * @return \Illuminate\Database\Eloquent\Builder<VehicleCategory>
     */
    private function rentalUnitTypesQuery(bool $tukTukLine): \Illuminate\Database\Eloquent\Builder
    {
        $lineId = $this->tukTukLineId();

        return VehicleCategory::query()
            ->ofKind(VehicleCategory::KIND_VEHICLE)
            ->when($tukTukLine, fn ($q) => $q->where(fn ($line) => $line->whereNull('product_category_id')->orWhere('product_category_id', $lineId)))
            ->when(! $tukTukLine, fn ($q) => $q->whereNotNull('product_category_id')->where('product_category_id', '!=', $lineId ?? 0));
    }

    /** @return Collection<int, VehicleCategory> */
    private function rentalUnitTypes(bool $tukTukLine): Collection
    {
        return $this->rentalUnitTypesQuery($tukTukLine)->active()->orderBy('sort_order')->get();
    }
}
