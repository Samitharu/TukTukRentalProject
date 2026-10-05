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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public listing/detail pages for both kinds of unit: tuk tuks under
 * /tuk-tuks, cabanas and rooms under /stays. A unit's slug opened under
 * the other kind's URL redirects to its canonical page.
 */
final class FleetController extends Controller
{
    public function index(Request $request): View
    {
        return view('fleet::front.index', [
            'vehicles' => $this->activeUnits(VehicleCategory::KIND_VEHICLE, $request),
            'categories' => VehicleCategory::query()->active()->ofKind(VehicleCategory::KIND_VEHICLE)->orderBy('sort_order')->get(),
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
    private function activeUnits(string $kind, Request $request): Collection
    {
        return Vehicle::query()
            ->active()
            ->ofKind($kind)
            // routeSlugs: the card links call slugFor() — one query per
            // vehicle without this (N+1).
            ->with(['images', 'category', 'routeSlugs' => fn ($q) => $q->where('locale', app()->getLocale())])
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->orderBy('plate_no')
            ->orderBy('id')
            ->get();
    }
}
