<?php

declare(strict_types=1);

namespace Modules\Availability\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\HasMapLocation;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property array<string,string> $name
 * @property string|null $address
 * @property string|null $google_maps_url
 * @property float|null $lat
 * @property float|null $lng
 * @property bool $is_pickup_point
 * @property bool $is_active
 */
final class BusinessLocation extends Model
{
    /** @use HasFactory<\Modules\Availability\Database\Factories\BusinessLocationFactory> */
    use HasFactory;
    use HasMapLocation;
    use HasTranslations;

    public array $translatable = ['name'];

    protected $fillable = [
        'name',
        'address',
        'google_maps_url',
        'lat',
        'lng',
        'is_pickup_point',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'is_pickup_point' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
