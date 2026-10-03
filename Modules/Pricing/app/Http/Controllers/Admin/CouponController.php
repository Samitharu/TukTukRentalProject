<?php

declare(strict_types=1);

namespace Modules\Pricing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Pricing\Http\Requests\Admin\StoreCouponRequest;
use Modules\Pricing\Http\Requests\Admin\UpdateCouponRequest;
use Modules\Pricing\Models\Coupon;

final class CouponController extends Controller
{
    public function index(): View
    {
        $this->authorize('pricing.manage');

        $coupons = Coupon::query()->orderByDesc('id')->get();

        return view('pricing::admin.coupons.index', compact('coupons'));
    }

    public function create(): View
    {
        $this->authorize('pricing.manage');

        return view('pricing::admin.coupons.create');
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] = mb_strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active', true);

        Coupon::query()->create($data);

        return redirect()->route('admin.coupons.index')->with('status', __('Coupon created.'));
    }

    public function edit(Coupon $coupon): View
    {
        $this->authorize('pricing.manage');

        return view('pricing::admin.coupons.edit', compact('coupon'));
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] = mb_strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        $coupon->update($data);

        return redirect()->route('admin.coupons.index')->with('status', __('Coupon updated.'));
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $this->authorize('pricing.manage');

        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('status', __('Coupon removed.'));
    }
}
