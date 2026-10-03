<?php

declare(strict_types=1);

namespace Modules\Package\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property array<string,string> $name
 * @property array<string,string>|null $description
 * @property float $price
 * @property string $pricing_unit
 * @property int $max_quantity
 * @property bool $is_active
 */
final class Addon extends Model
{
    /** @use HasFactory<\Modules\Package\Database\Factories\AddonFactory> */
    use HasFactory;
    use HasTranslations;

    public const string UNIT_FLAT = 'flat';

    public const string UNIT_PER_DAY = 'per_day';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'name',
        'description',
        'price',
        'pricing_unit',
        'max_quantity',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'max_quantity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function priceFor(int $days, int $quantity = 1): float
    {
        $unitPrice = $this->pricing_unit === self::UNIT_PER_DAY
            ? (float) $this->price * max($days, 1)
            : (float) $this->price;

        return round($unitPrice * $quantity, 2);
    }
}
