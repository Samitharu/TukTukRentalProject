<?php

declare(strict_types=1);

namespace Modules\Package\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Support\Seo;
use Modules\Fleet\Models\Vehicle;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PackageController extends Controller
{
    /**
     * Every visible category with its packages, in the admin's order —
     * categories without a listed package are left out.
     */
    public function index(): View
    {
        $categories = $this->visibleCategories()
            ->with(['packages' => fn ($q) => $this->listedPackages($q)])
            ->get()
            ->filter(fn (ProductCategory $category) => $category->packages->isNotEmpty())
            ->values();

        return view('package::front.index', [
            'categories' => $categories,
            'tabs' => $categories,
        ]);
    }

    public function category(string $locale, string $slug): View
    {
        $category = ProductCategory::findBySlug($locale, $slug);

        if ($category === null || ! $category->is_active) {
            throw new NotFoundHttpException();
        }

        $category->load(['routeSlugs', 'packages' => fn ($q) => $this->listedPackages($q)]);

        return view('package::front.category', [
            'category' => $category,
            'tabs' => $this->visibleCategories()->whereHas('packages', fn ($q) => $q->active()->currentlyValid())->get(),
            'image' => $category->imageUrl(),
            'alternates' => Seo::alternatesForModel($category, 'packages.category'),
            'schema' => [
                Seo::breadcrumbs([
                    [__('core::front.nav_home'), route('home')],
                    [__('core::front.nav_packages'), route('packages.index')],
                    [(string) $category->name, url()->current()],
                ]),
            ],
        ]);
    }

    public function pricing(): View
    {
        $packages = Package::query()
            ->active()
            ->currentlyValid()
            ->inVisibleCategory()
            ->with(['pricingTiers', 'routeSlugs' => fn ($q) => $q->where('locale', app()->getLocale())])
            ->orderBy('sort_order')
            ->get();

        return view('package::front.pricing', compact('packages'));
    }

    public function show(string $locale, string $slug): View
    {
        $package = Package::findBySlug($locale, $slug);

        if ($package === null || ! $package->is_active || ($package->productCategory !== null && ! $package->productCategory->is_active)) {
            throw new NotFoundHttpException;
        }

        $package->load(['pricingTiers', 'seasons', 'addons', 'images', 'routeSlugs', 'productCategory.routeSlugs']);

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
        $category = $package->productCategory;
        $categorySlug = $category?->slugFor(app()->getLocale());

        return view('package::front.show', [
            'package' => $package,
            'stayUnits' => $stayUnits,
            'image' => $image,
            'whatsappUrl' => $package->isBookableOnline() ? null : $this->whatsappUrl($package),
            'alternates' => Seo::alternatesForModel($package, 'packages.show'),
            'schema' => [
                $this->productSchema($package, $image),
                Seo::breadcrumbs(array_values(array_filter([
                    [__('core::front.nav_home'), route('home')],
                    [__('core::front.nav_packages'), route('packages.index')],
                    $categorySlug !== null ? [(string) $category->name, route('packages.category', $categorySlug)] : null,
                    [(string) $package->name, url()->current()],
                ]))),
            ],
        ]);
    }

    /**
     * @return Builder<ProductCategory>
     */
    private function visibleCategories(): Builder
    {
        return ProductCategory::query()
            ->active()
            ->with(['routeSlugs' => fn ($q) => $q->where('locale', app()->getLocale())])
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Relations\HasMany<Package>|Builder<Package>  $query
     */
    private function listedPackages($query): void
    {
        $query->active()
            ->currentlyValid()
            // routeSlugs: avoids one slugFor() query per package card.
            ->with(['pricingTiers', 'images', 'routeSlugs' => fn ($q) => $q->where('locale', app()->getLocale())])
            ->orderBy('sort_order');
    }

    /**
     * Activities are booked by message for now: WhatsApp click-to-chat
     * with the package named, in the customer's language.
     */
    private function whatsappUrl(Package $package): string
    {
        return 'https://wa.me/'.config('core.business.whatsapp')
            .'?text='.rawurlencode(__('core::front.packages_whatsapp_message', ['package' => $package->name]));
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
            'category' => $package->productCategory?->getTranslation('name', 'en', false) ?: ($package->isStay() ? 'Accommodation package' : 'Tuk tuk rental'),
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
