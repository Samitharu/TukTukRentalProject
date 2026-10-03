<?php

declare(strict_types=1);

namespace Modules\Pricing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Pricing\Http\Requests\Admin\StoreCurrencyRequest;
use Modules\Pricing\Http\Requests\Admin\UpdateExchangeRateRequest;
use Modules\Pricing\Models\Currency;

final class CurrencyController extends Controller
{
    public function index(): View
    {
        $this->authorize('pricing.manage');

        $currencies = Currency::query()->orderByDesc('is_base')->orderBy('code')->get();

        return view('pricing::admin.currencies.index', compact('currencies'));
    }

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] = mb_strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_base'] = false;

        Currency::query()->create($data);

        return redirect()->route('admin.currencies.index')->with('status', __('Currency added.'));
    }

    public function updateRate(UpdateExchangeRateRequest $request, Currency $currency): RedirectResponse
    {
        $currency->exchangeRates()->create([
            'rate' => $request->validated('rate'),
            'fetched_at' => now(),
        ]);

        return redirect()->route('admin.currencies.index')->with('status', __('Exchange rate updated.'));
    }

    public function makeBase(Currency $currency): RedirectResponse
    {
        $this->authorize('pricing.manage');

        Currency::query()->where('id', '!=', $currency->id)->update(['is_base' => false]);
        $currency->update(['is_base' => true, 'is_active' => true]);

        return redirect()->route('admin.currencies.index')->with('status', __('Base currency updated.'));
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        $this->authorize('pricing.manage');

        abort_if($currency->is_base, 403, __('Cannot remove the base currency.'));

        $currency->delete();

        return redirect()->route('admin.currencies.index')->with('status', __('Currency removed.'));
    }
}
