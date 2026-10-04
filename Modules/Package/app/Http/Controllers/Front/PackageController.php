<?php

declare(strict_types=1);

namespace Modules\Package\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
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

        $package->load(['pricingTiers', 'seasons', 'addons', 'images']);

        return view('package::front.show', compact('package'));
    }
}
