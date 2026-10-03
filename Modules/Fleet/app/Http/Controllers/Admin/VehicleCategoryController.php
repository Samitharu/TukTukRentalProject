<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Fleet\Http\Requests\Admin\StoreVehicleCategoryRequest;
use Modules\Fleet\Http\Requests\Admin\UpdateVehicleCategoryRequest;
use Modules\Fleet\Models\VehicleCategory;

final class VehicleCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', VehicleCategory::class);

        $categories = VehicleCategory::query()->withCount('vehicles')->orderBy('sort_order')->get();

        return view('fleet::admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        $this->authorize('create', VehicleCategory::class);

        return view('fleet::admin.categories.create');
    }

    public function store(StoreVehicleCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        VehicleCategory::query()->create($data);

        return redirect()->route('admin.fleet.categories.index')->with('status', __('Category created.'));
    }

    public function edit(VehicleCategory $category): View
    {
        $this->authorize('update', $category);

        return view('fleet::admin.categories.edit', compact('category'));
    }

    public function update(UpdateVehicleCategoryRequest $request, VehicleCategory $category): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $category->update($data);

        return redirect()->route('admin.fleet.categories.index')->with('status', __('Category updated.'));
    }

    public function destroy(VehicleCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return redirect()->route('admin.fleet.categories.index')->with('status', __('Category removed.'));
    }
}
