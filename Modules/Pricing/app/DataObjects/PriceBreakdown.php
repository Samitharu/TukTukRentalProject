<?php

declare(strict_types=1);

namespace Modules\Pricing\DataObjects;

/**
 * The full, itemized result of PricingService::calculate() — the single
 * shape rendered on the booking review step and re-validated server-side
 * at checkout. Never trust a client-submitted total (brief §7); this is
 * always recomputed from scratch from the stored package/season/addon
 * records.
 */
final readonly class PriceBreakdown
{
    /**
     * @param array<int, array{addon_id: int, name: string, quantity: int, amount: float, amount_eur: float|null}> $addonLines
     * @param array{base_amount: float, seasonal_adjustment: float, addons_total: float, transfer_fee: float, rental_subtotal: float, total: float}|null $eur
     *        the same bill from the EUR prices; null when a priced part has none
     */
    public function __construct(
        public string $currencyCode,
        public int $days,
        public float $baseAmount,
        public float $seasonalAdjustment,
        public array $addonLines,
        public float $addonsTotal,
        public float $deliveryFee,
        public float $total,
        public float $depositAmount,
        // Set only for an hourly package: the hours priced (days is then 1).
        public ?int $hours = null,
        // Airport pickups: the destination area's trip price.
        public float $transferFee = 0.0,
        public ?array $eur = null,
        // Taken off the total for booking a package offer's other package too.
        public float $offerDiscount = 0.0,
        // Set only for a package priced per person: the people priced.
        public ?int $people = null,
    ) {
    }

    public function rentalSubtotal(): float
    {
        return round($this->baseAmount + $this->seasonalAdjustment + $this->addonsTotal + $this->transferFee, 2);
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
            'people' => $this->people,
            'base_amount' => $this->baseAmount,
            'seasonal_adjustment' => $this->seasonalAdjustment,
            'addon_lines' => $this->addonLines,
            'addons_total' => $this->addonsTotal,
            'delivery_fee' => $this->deliveryFee,
            'transfer_fee' => $this->transferFee,
            'rental_subtotal' => $this->rentalSubtotal(),
            'offer_discount' => $this->offerDiscount,
            'total' => $this->total,
            'deposit_amount' => $this->depositAmount,
            'balance_due' => $this->balanceDue(),
            'eur' => $this->eur,
        ];
    }
}
