<?php

declare(strict_types=1);

namespace Modules\Pricing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $currency_id
 * @property string $from_code
 * @property float $rate
 * @property \Illuminate\Support\Carbon $fetched_at
 */
final class ExchangeRate extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $fillable = [
        'currency_id',
        'from_code',
        'rate',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'fetched_at' => 'datetime',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget('currencies:lkr_per_usd');
            Cache::forget('currencies:lkr_per_eur');
        });
    }
}
