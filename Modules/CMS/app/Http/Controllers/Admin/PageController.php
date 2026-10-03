<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\CMS\Http\Requests\Admin\StorePageRequest;
use Modules\CMS\Http\Requests\Admin\UpdatePageRequest;
use Modules\CMS\Models\Page;

final class PageController extends Controller
{
    public function index(): View
    {
        $this->authorize('cms.view');

        $pages = Page::query()->orderBy('title->en')->get();

        return view('cms::admin.pages.index', compact('pages'));
    }

    public function create(): View
    {
        $this->authorize('cms.manage');

        return view('cms::admin.pages.create');
    }

    public function store(StorePageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published', true);

        Page::query()->create($data);

        return redirect()->route('admin.pages.index')->with('status', __('Page created.'));
    }

    public function edit(Page $page): View
    {
        $this->authorize('cms.manage');

        return view('cms::admin.pages.edit', compact('page'));
    }

    public function update(UpdatePageRequest $request, Page $page): RedirectResponse
    {
        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');

        $page->update($data);

        return redirect()->route('admin.pages.index')->with('status', __('Page updated.'));
    }

    public function destroy(Page $page): RedirectResponse
    {
        $this->authorize('cms.manage');

        $page->routeSlugs()->delete();
        $page->delete();

        return redirect()->route('admin.pages.index')->with('status', __('Page removed.'));
    }
}
