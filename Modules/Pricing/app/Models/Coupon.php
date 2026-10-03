<?php

declare(strict_types=1);

namespace Modules\Pricing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $type
 * @property float $value
 * @property int|null $min_days
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_until
 * @property int|null $usage_limit
 * @property int $usage_count
 * @property bool $is_active
 */
final class Coupon extends Model
{
    /** @use HasFactory<\Modules\Pricing\Database\Factories\CouponFactory> */
    use HasFactory;

    public const string TYPE_FIXED = 'fixed';

    public const string TYPE_PERCENT = 'percent';

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_days',
        'valid_from',
        'valid_until',
        'usage_limit',
        'usage_count',
        'applies_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_days' => 'integer',
            // Explicit Y-m-d format — see AvailabilityBlackout::casts() for why.
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'applies_to' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Pure eligibility check — does NOT increment usage_count (that only
     * happens once a booking is actually confirmed, in Phase 4).
     */
    public function isValidFor(int $days, ?\DateTimeInterface $on = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $on ??= now();

        if ($this->min_days !== null && $days < $this->min_days) {
            return false;
        }

        if ($this->valid_from !== null && $this->valid_from->greaterThan($on)) {
            return false;
        }

        if ($this->valid_until !== null && $this->valid_until->lessThan($on)) {
            return false;
        }

        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function discountFor(float $subtotal): float
    {
        return $this->type === self::TYPE_PERCENT
            ? round($subtotal * ((float) $this->value / 100), 2)
            : min((float) $this->value, $subtotal);
    }
}
