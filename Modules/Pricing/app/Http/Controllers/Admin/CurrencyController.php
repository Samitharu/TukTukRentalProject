<?php

declare(strict_types=1);

namespace Modules\Pricing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Pricing\Http\Requests\Admin\UpdateExchangeRateRequest;
use Modules\Pricing\Models\Currency;

/**
 * The two rates staff set: LKR for 1 USD and LKR for 1 EUR. USD and EUR prices
 * are both entered by hand, so nothing else needs converting.
 */
final class CurrencyController extends Controller
{
    public function index(): View
    {
        $this->authorize('pricing.manage');

        return view('pricing::admin.currencies.index', [
            'rate' => Currency::lkrPerUsd(),
            'rateEur' => Currency::lkrPerEur(),
            'updatedAt' => Currency::query()->where('code', Currency::LKR)->first()?->exchangeRates()->max('fetched_at'),
        ]);
    }

    public function updateRate(UpdateExchangeRateRequest $request): RedirectResponse
    {
        $lkr = Currency::query()
            ->firstOrCreate(['code' => Currency::LKR], ['symbol' => 'Rs', 'is_base' => false, 'is_active' => true, 'decimal_places' => 0]);

        $lkr->exchangeRates()->createMany([
            ['from_code' => 'USD', 'rate' => $request->validated('rate'), 'fetched_at' => now()],
            ['from_code' => 'EUR', 'rate' => $request->validated('rate_eur'), 'fetched_at' => now()],
        ]);

        return redirect()->route('admin.currencies.index')->with('status', __('LKR rates updated.'));
    }
}
