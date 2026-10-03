<?php

declare(strict_types=1);

namespace Modules\Package\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $package_id
 * @property int $min_days
 * @property int|null $max_days
 * @property float $price
 */
final class PackagePricingTier extends Model
{
    protected $fillable = [
        'package_id',
        'min_days',
        'max_days',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'min_days' => 'integer',
            'max_days' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function covers(int $days): bool
    {
        return $days >= $this->min_days && ($this->max_days === null || $days <= $this->max_days);
    }
}
