<?php

declare(strict_types=1);

namespace Modules\Pricing\DataObjects;

/**
 * The full, itemized result of PricingService::calculate() — the single
 * shape rendered on the booking review step and re-validated server-side
 * at checkout. Never trust a client-submitted total (brief §7); this is
 * always recomputed from scratch from the stored package/season/addon/
 * coupon records.
 */
final readonly class PriceBreakdown
{
    /**
     * @param array<int, array{addon_id: int, name: string, quantity: int, amount: float}> $addonLines
     */
    public function __construct(
        public string $currencyCode,
        public int $days,
        public float $baseAmount,
        public float $seasonalAdjustment,
        public array $addonLines,
        public float $addonsTotal,
        public float $deliveryFee,
        public ?string $couponCode,
        public float $couponDiscount,
        public float $total,
        public float $depositAmount,
        // Set only for an hourly package: the hours priced (days is then 1).
        public ?int $hours = null,
    ) {
    }

    public function rentalSubtotal(): float
    {
        return round($this->baseAmount + $this->seasonalAdjustment + $this->addonsTotal, 2);
    }

    public function balanceDue(): float
    {
        return round($this->total - $this->depositAmount, 2);
    }

    public function toArray(): array
    {
        return [
            'currency_code' => $this->currencyCode,
            'days' => $this->days,
            'hours' => $this->hours,
            'base_amount' => $this->baseAmount,
            'seasonal_adjustment' => $this->seasonalAdjustment,
            'addon_lines' => $this->addonLines,
            'addons_total' => $this->addonsTotal,
            'delivery_fee' => $this->deliveryFee,
            'rental_subtotal' => $this->rentalSubtotal(),
            'coupon_code' => $this->couponCode,
            'coupon_discount' => $this->couponDiscount,
            'total' => $this->total,
            'deposit_amount' => $this->depositAmount,
            'balance_due' => $this->balanceDue(),
        ];
    }
}
