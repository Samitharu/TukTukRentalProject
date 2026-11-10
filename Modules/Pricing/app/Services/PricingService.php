<?php

declare(strict_types=1);

namespace Modules\Pricing\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Availability\Models\DeliveryZone;
use Modules\Availability\Models\TransferArea;
use Modules\Package\Models\Addon;
use Modules\Package\Models\Package;
use Modules\Package\Models\PackageOffer;
use Modules\Package\Models\PackagePricingTier;
use Modules\Package\Models\PackageSeason;
use Modules\Pricing\DataObjects\PriceBreakdown;

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
        ?DeliveryZone $deliveryZone = null,
        ?int $hours = null,
        ?TransferArea $transferArea = null,
        ?int $people = null,
    ): PriceBreakdown {
        // An hourly package is a single-day rental priced by the hour;
        // add-ons see it as the one day it is.
        $hours = $package->isHourly() ? max($hours ?? (int) $package->min_hours, 1) : null;
        $groupPriced = $package->isGroupPriced();
        $people = $package->pricing_model === Package::MODEL_PER_PERSON || $groupPriced ? max($people ?? 1, 1) : null;

        $endDate = $startDate->addDays(max($days - 1, 0));

        // Tiers count people for a group-priced or per-person package, hours
        // for an hourly one, days for any other.
        $tier = $this->tierFor($package, $groupPriced ? $people : ($hours ?? $people ?? $days));
        $season = $this->seasonFor($package, $startDate, $endDate);

        // A group's price is the price of the whole package: never multiplied
        // by people, hours or days (baseAmount() charges it once).
        $rateUnits = $groupPriced ? null : ($hours ?? $people);
        $baseAmount = $this->baseAmount($package, (float) ($tier?->price ?? 0), $days, $rateUnits);
        $seasonalAdjustment = $season?->modifierFor($baseAmount) ?? 0.0;
        [$addonLines, $addonsTotal] = $this->calculateAddons($package, $addonSelections, $days);
        $deliveryFee = (float) ($deliveryZone?->extra_fee ?? 0);
        // Airport pickup: package price (per trip) + the destination's price.
        $transferFee = $package->isTransfer() ? (float) ($transferArea?->price ?? 0) : 0.0;
        // Sri Lankan driving permit: once per booking, however long it is.
        $licenceFee = $package->licenceFee();

        $rentalSubtotal = round($baseAmount + $seasonalAdjustment + $addonsTotal + $transferFee + $licenceFee, 2);

        $total = round($rentalSubtotal + $deliveryFee, 2);
        $depositAmount = $this->calculateDeposit($package, $total);

        return new PriceBreakdown(
            currencyCode: config('pricing.default_currency'),
            days: $days,
            baseAmount: $baseAmount,
            seasonalAdjustment: $seasonalAdjustment,
            addonLines: $addonLines,
            addonsTotal: $addonsTotal,
            deliveryFee: $deliveryFee,
            total: $total,
            depositAmount: $depositAmount,
            hours: $hours,
            transferFee: $transferFee,
            eur: $this->eurAmounts($package, $tier, $season, $days, $rateUnits, $addonLines, $deliveryFee, $transferArea),
            people: $people,
            licenceFee: $licenceFee,
        );
    }

    /**
     * The same bill with a package offer's saving taken off, in each
     * currency. A saving larger than the bill makes it free, never negative.
     */
    public function withOffer(Package $package, PriceBreakdown $price, PackageOffer $offer): PriceBreakdown
    {
        $discount = min((float) $offer->discount, $price->total);
        $total = round($price->total - $discount, 2);
        $eur = $price->eur;

        if ($eur !== null) {
            $discountEur = min((float) $offer->discount_eur, $eur['total']);
            $eur = [...$eur, 'offer_discount' => $discountEur, 'total' => round($eur['total'] - $discountEur, 2)];
        }

        return new PriceBreakdown(
            currencyCode: $price->currencyCode,
            days: $price->days,
            baseAmount: $price->baseAmount,
            seasonalAdjustment: $price->seasonalAdjustment,
            addonLines: $price->addonLines,
            addonsTotal: $price->addonsTotal,
            deliveryFee: $price->deliveryFee,
            total: $total,
            depositAmount: $this->calculateDeposit($package, $total),
            hours: $price->hours,
            transferFee: $price->transferFee,
            eur: $eur,
            offerDiscount: $discount,
            people: $price->people,
            licenceFee: $price->licenceFee,
        );
    }

    /**
     * The same bill from the EUR prices staff entered, never a conversion.
     * Null when any priced part has no EUR price, so customers are shown USD
     * alone instead of a EUR total that leaves something out.
     *
     * @param array<int, array{amount_eur: float|null}> $addonLines
     * @return array{base_amount: float, seasonal_adjustment: float, addons_total: float, transfer_fee: float, rental_subtotal: float, total: float}|null
     */
    private function eurAmounts(Package $package, ?PackagePricingTier $tier, ?PackageSeason $season, int $days, ?int $rateUnits, array $addonLines, float $deliveryFee, ?TransferArea $transferArea): ?array
    {
        if ($tier !== null && $tier->price_eur === null) {
            return null;
        }

        $base = $this->baseAmount($package, (float) ($tier?->price_eur ?? 0), $days, $rateUnits);
        $seasonal = $season === null ? 0.0 : $season->eurModifierFor($base);
        $transfer = $package->isTransfer() && $transferArea !== null ? $transferArea->price_eur : 0.0;
        $licence = $package->licenceFeeEur();
        $addonAmounts = array_column($addonLines, 'amount_eur');

        // Delivery zones have no EUR fee.
        if ($seasonal === null || $transfer === null || $licence === null || in_array(null, $addonAmounts, true) || $deliveryFee > 0) {
            return null;
        }

        $addons = round(array_sum($addonAmounts), 2);
        $subtotal = round($base + $seasonal + $addons + (float) $transfer + $licence, 2);

        return [
            'base_amount' => $base,
            'seasonal_adjustment' => $seasonal,
            'addons_total' => $addons,
            'transfer_fee' => (float) $transfer,
            'licence_fee' => $licence,
            'rental_subtotal' => $subtotal,
            'total' => $subtotal,
        ];
    }

    /** $rate is the tier's price in one currency; $rateUnits the hours or people it is multiplied by, if any. */
    private function baseAmount(Package $package, float $rate, int $days, ?int $rateUnits): float
    {
        if ($rateUnits !== null) {
            // e.g. 1-3 h at 8/h, 4+ h at 6/h.
            return round($rate * $rateUnits, 2);
        }

        // One price for the package: a fixed bundle, or a group's price.
        if ($package->pricing_model === Package::MODEL_FIXED_BUNDLE || $package->isGroupPriced()) {
            return $rate;
        }

        $units = match ($package->pricing_model) {
            Package::MODEL_PER_WEEK => (int) ceil($days / 7),
            Package::MODEL_PER_MONTH => (int) ceil($days / 30),
            default => $days, // per_day, tiered
        };

        return round($rate * $units, 2);
    }

    /**
     * The tier covering $units — days for most packages, hours for an hourly
     * one, people for a per-person one (tier columns are named for the original, day case).
     */
    private function tierFor(Package $package, int $units): ?PackagePricingTier
    {
        return $package->pricingTiers()
            ->where('min_days', '<=', $units)
            ->where(fn ($q) => $q->whereNull('max_days')->orWhere('max_days', '>=', $units))
            ->first();
    }

    private function seasonFor(Package $package, CarbonImmutable $start, CarbonImmutable $end): ?PackageSeason
    {
        return $package->seasons()
            ->overlapping($start, $end)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->first(fn (PackageSeason $season) => $season->appliesToWeekday($start));
    }

    /**
     * @param array<int, int> $addonSelections
     * @return array{0: array<int, array{addon_id:int, name:string, quantity:int, amount:float, amount_eur:float|null}>, 1: float}
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
                'amount_eur' => $addon->eurPriceFor($days, $quantity),
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
}
