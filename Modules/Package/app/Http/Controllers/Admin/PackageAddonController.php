<?php

declare(strict_types=1);

namespace Modules\Package\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Package\Models\Package;

final class PackageAddonController extends Controller
{
    public function update(Request $request, Package $package): RedirectResponse
    {
        $this->authorize('update', $package);

        $offered = array_map('intval', $request->input('offered_addon_ids', []));
        $included = array_map('intval', $request->input('included_addon_ids', []));

        $sync = collect($offered)->mapWithKeys(fn (int $id) => [$id => ['is_included' => in_array($id, $included, true)]]);

        $package->addons()->sync($sync);

        return redirect()->route('admin.packages.edit', $package)->with('status', __('Add-ons updated.'));
    }
}
