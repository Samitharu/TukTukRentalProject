<?php

declare(strict_types=1);

namespace Modules\Availability\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property array<string,string> $name
 * @property float $extra_fee
 * @property bool $is_active
 */
final class DeliveryZone extends Model
{
    /** @use HasFactory<\Modules\Availability\Database\Factories\DeliveryZoneFactory> */
    use HasFactory;
    use HasTranslations;

    public array $translatable = ['name'];

    protected $fillable = [
        'name',
        'extra_fee',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'extra_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
