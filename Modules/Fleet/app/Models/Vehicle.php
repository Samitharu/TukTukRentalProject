<?php

declare(strict_types=1);

namespace Modules\Fleet\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Localization\Support\HasTranslatableSlug;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property int $category_id
 * @property int|null $base_location_id
 * @property array<string,string> $name
 * @property string $plate_no
 * @property string|null $model
 * @property int|null $year
 * @property string|null $colour
 * @property int $seats
 * @property string $transmission
 * @property string $fuel_type
 * @property array<int,string>|null $features
 * @property array<string,string>|null $description
 * @property string $status
 */
final class Vehicle extends Model
{
    /** @use HasFactory<\Modules\Fleet\Database\Factories\VehicleFactory> */
    use HasFactory;
    use HasTranslatableSlug;
    use HasTranslations;
    use SoftDeletes;

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_MAINTENANCE = 'maintenance';

    public const string STATUS_RETIRED = 'retired';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'category_id',
        'base_location_id',
        'name',
        'plate_no',
        'model',
        'year',
        'colour',
        'seats',
        'transmission',
        'fuel_type',
        'features',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'year' => 'integer',
            'seats' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $vehicle) => $vehicle->syncSlugs());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(VehicleImage::class)->orderBy('sort_order');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(VehicleMaintenanceLog::class);
    }

    public function primaryImage(): ?VehicleImage
    {
        return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }
}
