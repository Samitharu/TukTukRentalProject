<?php

declare(strict_types=1);

namespace Modules\Package\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Pricing\Support\Money;

/**
 * @property int $id
 * @property int $package_id
 * @property int $min_days
 * @property int|null $max_days
 * @property float $price
 * @property float|null $price_eur
 */
final class PackagePricingTier extends Model
{
    protected $fillable = [
        'package_id',
        'min_days',
        'max_days',
        'price',
        'price_eur',
    ];

    protected function casts(): array
    {
        return [
            'min_days' => 'integer',
            'max_days' => 'integer',
            'price' => 'decimal:2',
            'price_eur' => 'decimal:2',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function displayPrice(): string
    {
        return Money::dual($this->price, $this->price_eur);
    }

    /** The price of $units hours (or people) at this tier's rate, in both currencies. */
    public function displayPriceFor(int $units): string
    {
        return Money::dual(
            round((float) $this->price * $units, 2),
            $this->price_eur === null ? null : round((float) $this->price_eur * $units, 2),
        );
    }

    public function covers(int $days): bool
    {
        return $days >= $this->min_days && ($this->max_days === null || $days <= $this->max_days);
    }
}
