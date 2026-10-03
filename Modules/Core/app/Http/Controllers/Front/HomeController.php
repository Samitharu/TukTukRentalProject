<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\CMS\Models\Testimonial;
use Modules\Fleet\Models\Vehicle;
use Modules\Package\Models\Package;

/**
 * Deliberate exception to Core's normal "nothing depends on Core" rule
 * (docs/02-modules.md): a homepage is inherently cross-cutting — it
 * teases the fleet, packages, and testimonials all at once — and none of
 * those three domain modules is the "right" owner of it. Adding a
 * dedicated module for one controller would be over-engineering; this is
 * the one place in the app allowed to reach across Fleet/Package/CMS like
 * this, and it exists only because the alternative is worse.
 */
final class HomeController extends Controller
{
    public function __invoke(): View
    {
        $featuredPackages = Package::query()
            ->active()
            ->currentlyValid()
            ->where('is_featured', true)
            ->with('images')
            ->orderBy('sort_order')
            ->limit(3)
            ->get();

        $vehicles = Vehicle::query()->active()->with('images')->limit(4)->get();
        $testimonials = Testimonial::query()->approved()->latest()->limit(6)->get();

        return view('core::front.home', compact('featuredPackages', 'vehicles', 'testimonials'));
    }
}
