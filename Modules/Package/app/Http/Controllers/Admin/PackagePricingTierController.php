<?php

declare(strict_types=1);

namespace Modules\Package\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Package\Http\Requests\Admin\StorePricingTierRequest;
use Modules\Package\Models\Package;
use Modules\Package\Models\PackagePricingTier;
use Modules\Package\Services\PackagePricingTierValidator;

final class PackagePricingTierController extends Controller
{
    public function __construct(private readonly PackagePricingTierValidator $validator)
    {
    }

    public function store(StorePricingTierRequest $request, Package $package): RedirectResponse
    {
        $proposed = $package->pricingTiers()->get(['min_days', 'max_days'])
            ->push((object) $request->validated())
            ->map(fn ($tier) => ['min_days' => (int) $tier->min_days, 'max_days' => $tier->max_days !== null ? (int) $tier->max_days : null])
            ->all();

        $errors = $this->validator->validate($proposed);

        if ($errors !== []) {
            return back()->withErrors(['min_days' => implode(' ', $errors)])->withInput();
        }

        $package->pricingTiers()->create($request->validated());

        return redirect()->route('admin.packages.edit', $package)->with('status', __('Pricing tier added.'));
    }

    public function destroy(Package $package, PackagePricingTier $tier): RedirectResponse
    {
        $this->authorize('update', $package);

        abort_unless($tier->package_id === $package->id, 404);

        $tier->delete();

        return redirect()->route('admin.packages.edit', $package)->with('status', __('Pricing tier removed.'));
    }
}
