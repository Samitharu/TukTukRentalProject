<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Modules\CMS\Models\Review;
use Modules\Core\Support\Seo;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Package\Models\Package;

/**
 * Deliberate exception to Core's normal "nothing depends on Core" rule
 * (docs/02-modules.md): a homepage is inherently cross-cutting — it
 * teases the fleet, packages, and reviews all at once — and none of
 * those three domain modules is the "right" owner of it. Adding a
 * dedicated module for one controller would be over-engineering; this is
 * the one place in the app allowed to reach across Fleet/Package/CMS like
 * this, and it exists only because the alternative is worse.
 */
final class HomeController extends Controller
{
    private const MAX_CUSTOMER_REVIEWS = 12;

    public function __invoke(): View
    {
        // Every package staff tick "featured", in their sort order.
        $featuredPackages = Package::query()
            ->active()
            ->currentlyValid()
            ->inVisibleCategory()
            ->where('is_featured', true)
            ->with(['images', 'liveOffers', 'pricingTiers', 'routeSlugs' => fn ($q) => $q->where('locale', app()->getLocale())])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // The registered rental vehicles (SUVs, bikes) — tuk tuks aren't
        // registered, and cabanas and rooms have their own page.
        $vehicles = Vehicle::query()->active()->tracked()->ofKind(VehicleCategory::KIND_VEHICLE)->with('images')->limit(4)->get();
        $customerReviews = $this->customerReviews();

        $schema = [Seo::website(), Seo::organization()];

        return view('core::front.home', compact('featuredPackages', 'vehicles', 'customerReviews', 'schema'));
    }

    /**
     * Approved booking reviews (submitted by customers after a rental),
     * newest first, for the homepage slider. Rating-only reviews are skipped
     * — a slide without any words says nothing to the next traveller.
     *
     * @return Collection<int, array{name: string, initials: string, country: ?string, rating: int, content: string, verified: bool, date: \Illuminate\Support\Carbon|null}>
     */
    private function customerReviews(): Collection
    {
        return Review::query()
            ->approved()
            ->where('content', '!=', '')
            ->latest()
            ->limit(self::MAX_CUSTOMER_REVIEWS)
            ->get()
            ->map(fn (Review $review): array => $this->slide(
                $review->customer_name,
                $review->country,
                $review->rating,
                (string) $review->content,
                $review->booking_id !== null,
                $review->created_at,
            ))
            ->filter(fn (array $slide): bool => $slide['content'] !== '')
            ->values();
    }

    /**
     * @return array{name: string, initials: string, country: ?string, rating: int, content: string, verified: bool, date: \Illuminate\Support\Carbon|null}
     */
    private function slide(?string $name, ?string $country, int $rating, string $content, bool $verified, mixed $date): array
    {
        $name = trim((string) $name) !== '' ? trim((string) $name) : __('core::front.review_anonymous');
        $initials = collect(preg_split('/\s+/', $name) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return [
            'name' => $name,
            'initials' => $initials,
            'country' => $this->countryName($country),
            'rating' => max(0, min(5, $rating)),
            'content' => trim($content),
            'verified' => $verified,
            'date' => $date,
        ];
    }

    /**
     * Both tables store an ISO 3166 alpha-2 code; show the localised
     * country name when intl is available, the bare code otherwise.
     */
    private function countryName(?string $code): ?string
    {
        if (! is_string($code) || $code === '') {
            return null;
        }

        if (strlen($code) === 2 && class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('-'.strtoupper($code), app()->getLocale());

            if (is_string($name) && $name !== '' && strcasecmp($name, $code) !== 0) {
                return $name;
            }
        }

        return strtoupper($code);
    }
}
