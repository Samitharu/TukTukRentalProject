<?php

declare(strict_types=1);

namespace Modules\Package\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\Core\Support\Seo;
use Modules\Fleet\Models\Vehicle;
use Modules\Package\Models\Package;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PackageController extends Controller
{
    public function index(): View
    {
        $packages = Package::query()
            ->active()
            ->currentlyValid()
            // routeSlugs: avoids one slugFor() query per package card.
            ->with(['pricingTiers', 'images', 'routeSlugs' => fn ($q) => $q->where('locale', app()->getLocale())])
            ->orderBy('sort_order')
            ->get();

        return view('package::front.index', compact('packages'));
    }

    public function pricing(): View
    {
        $packages = Package::query()
            ->active()
            ->currentlyValid()
            ->with(['pricingTiers', 'routeSlugs' => fn ($q) => $q->where('locale', app()->getLocale())])
            ->orderBy('sort_order')
            ->get();

        return view('package::front.pricing', compact('packages'));
    }

    public function show(string $locale, string $slug): View
    {
        $package = Package::findBySlug($locale, $slug);

        if ($package === null || ! $package->is_active) {
            throw new NotFoundHttpException;
        }

        $package->load(['pricingTiers', 'seasons', 'addons', 'images', 'routeSlugs']);

        // "Where you'll stay": the cabanas/rooms this package can be booked
        // into, each with its own map pin.
        $stayUnits = $package->isStay()
            ? Vehicle::query()
                ->whereIn('id', $package->eligibleVehicleIds())
                ->with(['images', 'routeSlugs' => fn ($q) => $q->where('locale', app()->getLocale())])
                ->orderBy('id')
                ->get()
            : collect();

        $image = Seo::storageImage($package->primaryImage()?->path);

        return view('package::front.show', [
            'package' => $package,
            'stayUnits' => $stayUnits,
            'image' => $image,
            'alternates' => Seo::alternatesForModel($package, 'packages.show'),
            'schema' => [
                $this->productSchema($package, $image),
                Seo::breadcrumbs([
                    [__('core::front.nav_home'), route('home')],
                    [__('core::front.nav_packages'), route('packages.index')],
                    [(string) $package->name, url()->current()],
                ]),
            ],
        ]);
    }

    /**
     * schema.org Product with an AggregateOffer spanning the package's
     * pricing tiers ("from X per day/night"), so search results can show
     * the starting price.
     *
     * @return array<string, mixed>
     */
    private function productSchema(Package $package, ?string $image): array
    {
        $prices = $package->pricingTiers->pluck('price')->map(fn ($price) => (float) $price)->filter(fn (float $price) => $price > 0);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string) $package->name,
            'description' => Seo::description((string) $package->description) ?: null,
            'image' => $image ?? Seo::defaultImage(),
            'category' => $package->isStay() ? 'Accommodation package' : 'Tuk tuk rental',
            'brand' => ['@type' => 'Brand', 'name' => config('app.name')],
            'offers' => $prices->isEmpty() ? null : array_filter([
                '@type' => 'AggregateOffer',
                'priceCurrency' => config('pricing.default_currency'),
                'lowPrice' => $prices->min(),
                'highPrice' => $prices->max(),
                'offerCount' => $prices->count(),
                'availability' => 'https://schema.org/InStock',
                'url' => url()->current(),
                'priceValidUntil' => $package->valid_until?->toDateString(),
            ], fn ($value) => $value !== null),
        ], fn ($value) => $value !== null);
    }
}
