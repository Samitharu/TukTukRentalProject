<?php

declare(strict_types=1);

namespace Modules\Package\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Package\Http\Requests\Admin\StoreAddonRequest;
use Modules\Package\Http\Requests\Admin\UpdateAddonRequest;
use Modules\Package\Models\Addon;

final class AddonController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Addon::class);

        $addons = Addon::query()->orderBy('sort_order')->get();

        return view('package::admin.addons.index', compact('addons'));
    }

    public function create(): View
    {
        $this->authorize('create', Addon::class);

        return view('package::admin.addons.create');
    }

    public function store(StoreAddonRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        Addon::query()->create($data);

        return redirect()->route('admin.addons.index')->with('status', __('Add-on created.'));
    }

    public function edit(Addon $addon): View
    {
        $this->authorize('update', $addon);

        return view('package::admin.addons.edit', compact('addon'));
    }

    public function update(UpdateAddonRequest $request, Addon $addon): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $addon->update($data);

        return redirect()->route('admin.addons.index')->with('status', __('Add-on updated.'));
    }

    public function destroy(Addon $addon): RedirectResponse
    {
        $this->authorize('delete', $addon);

        $addon->delete();

        return redirect()->route('admin.addons.index')->with('status', __('Add-on removed.'));
    }
}
