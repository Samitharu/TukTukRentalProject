<?php

declare(strict_types=1);

namespace Modules\Pricing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $code
 * @property string $symbol
 * @property bool $is_base
 * @property bool $is_active
 * @property int $decimal_places
 */
final class Currency extends Model
{
    /** @use HasFactory<\Modules\Pricing\Database\Factories\CurrencyFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'symbol',
        'is_base',
        'is_active',
        'decimal_places',
    ];

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'decimal_places' => 'integer',
        ];
    }

    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function base(): self
    {
        return Cache::rememberForever('currencies:base', fn () => self::query()->where('is_base', true)->firstOrFail());
    }

    public function latestRate(): ?float
    {
        if ($this->is_base) {
            return 1.0;
        }

        $rate = $this->exchangeRates()->orderByDesc('fetched_at')->first();

        return $rate?->rate !== null ? (float) $rate->rate : null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('currencies:base'));
    }
}
