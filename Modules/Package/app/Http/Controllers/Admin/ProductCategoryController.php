<?php

declare(strict_types=1);

namespace Modules\Package\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Package\Http\Requests\Admin\ProductCategoryRequest;
use Modules\Package\Models\ProductCategory;
use Modules\Package\Services\ProductCategoryImageService;

final class ProductCategoryController extends Controller
{
    public function __construct(private readonly ProductCategoryImageService $images)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', ProductCategory::class);

        $categories = ProductCategory::query()->withCount('packages')->orderBy('sort_order')->orderBy('id')->get();

        return view('package::admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        $this->authorize('create', ProductCategory::class);

        return view('package::admin.categories.create');
    }

    public function store(ProductCategoryRequest $request): RedirectResponse
    {
        $category = ProductCategory::query()->create([
            ...$request->safe()->except(['image', 'remove_image']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->hasFile('image')) {
            $this->images->replace($category, $request->file('image'));
        }

        return redirect()->route('admin.package-categories.index')->with('status', __('Category created — now add packages to it.'));
    }

    public function edit(ProductCategory $category): View
    {
        $this->authorize('update', $category);

        return view('package::admin.categories.edit', [
            'category' => $category,
            'hasPackages' => $category->packages()->withTrashed()->exists(),
        ]);
    }

    public function update(ProductCategoryRequest $request, ProductCategory $category): RedirectResponse
    {
        $category->update([
            ...$request->safe()->except(['image', 'remove_image']),
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->hasFile('image')) {
            $this->images->replace($category, $request->file('image'));
        } elseif ($request->boolean('remove_image')) {
            $this->images->delete($category);
        }

        return redirect()->route('admin.package-categories.index')->with('status', __('Category updated.'));
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $this->images->delete($category);
        $category->delete();

        return redirect()->route('admin.package-categories.index')->with('status', __('Category removed.'));
    }
}
