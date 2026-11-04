<?php

declare(strict_types=1);

namespace Modules\Availability\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Pricing\Support\Money;
use Spatie\Translatable\HasTranslations;

/**
 * An airport pickup destination (Negombo, Colombo, Kandy…). Its price is
 * the trip price. `duration_minutes` is not set by staff: vehicles come from
 * partners, so it only gives a trip's booking an end time (2 hours by default).
 *
 * @property int $id
 * @property array<string,string> $name
 * @property float $price
 * @property float|null $price_eur
 * @property int $duration_minutes
 * @property int $sort_order
 * @property bool $is_active
 */
final class TransferArea extends Model
{
    /** @use HasFactory<\Modules\Availability\Database\Factories\TransferAreaFactory> */
    use HasFactory;
    use HasTranslations;

    public array $translatable = ['name'];

    protected $fillable = [
        'name',
        'price',
        'price_eur',
        'duration_minutes',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_eur' => 'decimal:2',
            'duration_minutes' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function displayPrice(): string
    {
        return Money::dual($this->price, $this->price_eur);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
