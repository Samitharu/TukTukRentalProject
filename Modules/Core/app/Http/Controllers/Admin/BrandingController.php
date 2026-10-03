<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Core\Http\Requests\Admin\UpdateBrandingRequest;
use Modules\Core\Models\SiteSetting;
use Modules\Core\Services\SiteBrandingService;

final class BrandingController extends Controller
{
    public function __construct(private readonly SiteBrandingService $branding)
    {
    }

    public function edit(): View
    {
        $this->authorize('settings.manage');

        return view('core::admin.branding.edit', ['settings' => SiteSetting::current()]);
    }

    public function update(UpdateBrandingRequest $request): RedirectResponse
    {
        $settings = SiteSetting::current();

        if ($request->hasFile('logo')) {
            $this->branding->updateLogo($settings, $request->file('logo'));
        }

        if ($request->hasFile('hero_image')) {
            $this->branding->updateHeroImage($settings, $request->file('hero_image'));
        }

        return redirect()->route('admin.branding.edit')->with('status', __('Branding updated.'));
    }

    public function removeLogo(): RedirectResponse
    {
        $this->authorize('settings.manage');

        $this->branding->removeLogo(SiteSetting::current());

        return redirect()->route('admin.branding.edit')->with('status', __('Logo removed.'));
    }

    public function removeHeroImage(): RedirectResponse
    {
        $this->authorize('settings.manage');

        $this->branding->removeHeroImage(SiteSetting::current());

        return redirect()->route('admin.branding.edit')->with('status', __('Hero image removed.'));
    }
}
