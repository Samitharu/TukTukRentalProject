<?php

declare(strict_types=1);

namespace Modules\Package\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $package_id
 * @property string $name
 * @property \Illuminate\Support\Carbon $starts_on
 * @property \Illuminate\Support\Carbon $ends_on
 * @property string $price_modifier_type
 * @property float $price_modifier_value
 * @property int $weekday_mask
 * @property int $priority
 */
final class PackageSeason extends Model
{
    public const string MODIFIER_FIXED = 'fixed';

    public const string MODIFIER_PERCENT = 'percent';

    protected $fillable = [
        'package_id',
        'name',
        'starts_on',
        'ends_on',
        'price_modifier_type',
        'price_modifier_value',
        'weekday_mask',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            // Explicit Y-m-d format — see AvailabilityBlackout::casts() for why.
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'price_modifier_value' => 'decimal:2',
            'weekday_mask' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query->where('starts_on', '<=', $end)->where('ends_on', '>=', $start);
    }

    /**
     * Bit 0 = Monday ... bit 6 = Sunday (ISO-8601 weekday numbering minus 1).
     */
    public function appliesToWeekday(\DateTimeInterface $date): bool
    {
        $isoWeekday = (int) $date->format('N');

        return (bool) ($this->weekday_mask & (1 << ($isoWeekday - 1)));
    }

    public function modifierFor(float $baseAmount): float
    {
        return $this->price_modifier_type === self::MODIFIER_PERCENT
            ? round($baseAmount * ((float) $this->price_modifier_value / 100), 2)
            : (float) $this->price_modifier_value;
    }
}
