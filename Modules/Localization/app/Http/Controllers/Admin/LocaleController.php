<?php

declare(strict_types=1);

namespace Modules\Localization\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Localization\Http\Requests\Admin\StoreLocaleRequest;
use Modules\Localization\Http\Requests\Admin\UpdateLocaleRequest;
use Modules\Localization\Models\Locale;

final class LocaleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Locale::class);

        $locales = Locale::query()->orderBy('sort_order')->get();

        return view('localization::admin.locales.index', compact('locales'));
    }

    public function create(): View
    {
        $this->authorize('create', Locale::class);

        return view('localization::admin.locales.create');
    }

    public function store(StoreLocaleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] = mb_strtolower($data['code']);
        $data['is_active'] = $request->boolean('is_active', true);

        Locale::query()->create($data);

        return redirect()
            ->route('admin.locales.index')
            ->with('status', __('Locale added.'));
    }

    public function edit(Locale $locale): View
    {
        $this->authorize('update', $locale);

        return view('localization::admin.locales.edit', compact('locale'));
    }

    public function update(UpdateLocaleRequest $request, Locale $locale): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        // A locale cannot be deactivated while it's the default — there must
        // always be exactly one active default customers land on.
        if ($locale->is_default) {
            $data['is_active'] = true;
        }

        $locale->update($data);

        return redirect()
            ->route('admin.locales.index')
            ->with('status', __('Locale updated.'));
    }

    public function makeDefault(Locale $locale): RedirectResponse
    {
        $this->authorize('update', $locale);

        Locale::query()->where('id', '!=', $locale->id)->update(['is_default' => false]);
        $locale->update(['is_default' => true, 'is_active' => true]);

        return redirect()
            ->route('admin.locales.index')
            ->with('status', __('Default locale updated.'));
    }

    public function destroy(Locale $locale): RedirectResponse
    {
        $this->authorize('delete', $locale);

        $locale->delete();

        return redirect()
            ->route('admin.locales.index')
            ->with('status', __('Locale removed.'));
    }
}
