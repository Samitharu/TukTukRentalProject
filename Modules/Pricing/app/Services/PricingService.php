<?php

declare(strict_types=1);

namespace Modules\Pricing\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Availability\Models\DeliveryZone;
use Modules\Package\Models\Addon;
use Modules\Package\Models\Package;
use Modules\Package\Models\PackageSeason;
use Modules\Pricing\DataObjects\PriceBreakdown;
use Modules\Pricing\Models\Coupon;
use Modules\Pricing\Models\Currency;

/**
 * The single authoritative price calculator (brief §7: "prices always
 * recalculated server-side; never trust client totals"). Every caller —
 * the admin package-preview, the public price-recalculation JSON endpoint
 * (Phase 5), and the booking checkout (Phase 4) — goes through this one
 * class, so there is exactly one implementation of "how much does this
 * booking cost" to get right and to test.
 */
final class PricingService
{
    /**
     * @param array<int, int> $addonSelections addon_id => quantity
     */
    public function calculate(
        Package $package,
        CarbonImmutable $startDate,
        int $days,
        array $addonSelections = [],
        ?Coupon $coupon = null,
        ?DeliveryZone $deliveryZone = null,
        ?string $currencyCode = null,
        ?int $hours = null,
    ): PriceBreakdown {
        // An hourly package is a single-day rental priced by the hour;
        // add-ons and coupons see it as the one day it is.
        $hours = $package->isHourly() ? max($hours ?? (int) $package->min_hours, 1) : null;

        $endDate = $startDate->addDays(max($days - 1, 0));
        $currencyCode ??= config('pricing.default_currency');

        $baseAmount = $this->calculateBaseAmount($package, $days, $hours);
        $seasonalAdjustment = $this->calculateSeasonalAdjustment($package, $startDate, $endDate, $baseAmount);
        [$addonLines, $addonsTotal] = $this->calculateAddons($package, $addonSelections, $days);
        $deliveryFee = (float) ($deliveryZone?->extra_fee ?? 0);

        $rentalSubtotal = round($baseAmount + $seasonalAdjustment + $addonsTotal, 2);

        $couponDiscount = 0.0;
        $couponCode = null;
        if ($coupon !== null && $coupon->isValidFor($days, $startDate)) {
            $couponDiscount = $coupon->discountFor($rentalSubtotal);
            $couponCode = $coupon->code;
        }

        $total = round($rentalSubtotal - $couponDiscount + $deliveryFee, 2);
        $depositAmount = $this->calculateDeposit($package, $total);

        $breakdown = new PriceBreakdown(
            currencyCode: config('pricing.default_currency'),
            days: $days,
            baseAmount: $baseAmount,
            seasonalAdjustment: $seasonalAdjustment,
            addonLines: $addonLines,
            addonsTotal: $addonsTotal,
            deliveryFee: $deliveryFee,
            couponCode: $couponCode,
            couponDiscount: $couponDiscount,
            total: $total,
            depositAmount: $depositAmount,
            hours: $hours,
        );

        return $currencyCode === config('pricing.default_currency')
            ? $breakdown
            : $this->convert($breakdown, $currencyCode);
    }

    private function calculateBaseAmount(Package $package, int $days, ?int $hours): float
    {
        if ($hours !== null) {
            // Tiers count hours here: e.g. 1-3 h at 8/h, 4+ h at 6/h.
            return round($this->rateFor($package, $hours) * $hours, 2);
        }

        if ($package->pricing_model === Package::MODEL_FIXED_BUNDLE) {
            $tier = $package->pricingTiers()->where('min_days', '<=', $days)
                ->where(fn ($q) => $q->whereNull('max_days')->orWhere('max_days', '>=', $days))
                ->first();

            return (float) ($tier?->price ?? 0);
        }

        $rate = $this->rateFor($package, $days);
        $units = match ($package->pricing_model) {
            Package::MODEL_PER_WEEK => (int) ceil($days / 7),
            Package::MODEL_PER_MONTH => (int) ceil($days / 30),
            default => $days, // per_day, tiered
        };

        return round($rate * $units, 2);
    }

    /**
     * The rate of the tier covering $units — days for most packages, hours
     * for an hourly one (tier columns are named for the original, day case).
     */
    private function rateFor(Package $package, int $units): float
    {
        $tier = $package->pricingTiers()
            ->where('min_days', '<=', $units)
            ->where(fn ($q) => $q->whereNull('max_days')->orWhere('max_days', '>=', $units))
            ->first();

        return (float) ($tier?->price ?? 0);
    }

    private function calculateSeasonalAdjustment(Package $package, CarbonImmutable $start, CarbonImmutable $end, float $baseAmount): float
    {
        $applicable = $package->seasons()
            ->overlapping($start, $end)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->first(fn (PackageSeason $season) => $season->appliesToWeekday($start));

        return $applicable?->modifierFor($baseAmount) ?? 0.0;
    }

    /**
     * @param array<int, int> $addonSelections
     * @return array{0: array<int, array{addon_id:int, name:string, quantity:int, amount:float}>, 1: float}
     */
    private function calculateAddons(Package $package, array $addonSelections, int $days): array
    {
        if ($addonSelections === []) {
            return [[], 0.0];
        }

        $offeredAddonIds = $package->addons()->pluck('addons.id')->all();
        $lines = [];
        $total = 0.0;

        /** @var Collection<int, Addon> $addons */
        $addons = Addon::query()->whereIn('id', array_keys($addonSelections))->get()->keyBy('id');

        foreach ($addonSelections as $addonId => $quantity) {
            if ($quantity < 1 || ! in_array($addonId, $offeredAddonIds, true)) {
                continue;
            }

            $addon = $addons->get($addonId);

            if ($addon === null) {
                continue;
            }

            $quantity = min($quantity, $addon->max_quantity);
            $amount = $addon->priceFor($days, $quantity);

            $lines[] = [
                'addon_id' => $addon->id,
                'name' => $addon->name,
                'quantity' => $quantity,
                'amount' => $amount,
            ];
            $total += $amount;
        }

        return [$lines, round($total, 2)];
    }

    private function calculateDeposit(Package $package, float $total): float
    {
        if (! config('pricing.online_payment_enabled') || $package->deposit_amount === null) {
            return 0.0;
        }

        $deposit = $package->deposit_is_percent
            ? round($total * ((float) $package->deposit_amount / 100), 2)
            : (float) $package->deposit_amount;

        return min($deposit, $total);
    }

    private function convert(PriceBreakdown $breakdown, string $targetCurrencyCode): PriceBreakdown
    {
        $currency = Currency::query()->where('code', $targetCurrencyCode)->active()->first();
        $rate = $currency?->latestRate();

        if ($currency === null || $rate === null) {
            return $breakdown;
        }

        $round = fn (float $amount) => round($amount * $rate, $currency->decimal_places);

        return new PriceBreakdown(
            currencyCode: $currency->code,
            days: $breakdown->days,
            baseAmount: $round($breakdown->baseAmount),
            seasonalAdjustment: $round($breakdown->seasonalAdjustment),
            addonLines: array_map(
                fn (array $line) => [...$line, 'amount' => $round($line['amount'])],
                $breakdown->addonLines,
            ),
            addonsTotal: $round($breakdown->addonsTotal),
            deliveryFee: $round($breakdown->deliveryFee),
            couponCode: $breakdown->couponCode,
            couponDiscount: $round($breakdown->couponDiscount),
            total: $round($breakdown->total),
            depositAmount: $round($breakdown->depositAmount),
            hours: $breakdown->hours,
        );
    }
}
