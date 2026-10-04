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
 * A bookable unit. Historically only tuk tuks (hence the name and table),
 * now also stays — cabanas and rooms — distinguished by their category's
 * `kind`. Both kinds share the same one-unit-per-date reservation engine;
 * vehicle-only columns (plate, transmission, fuel) are null for stays.
 *
 * @property int $id
 * @property int $category_id
 * @property int|null $base_location_id
 * @property array<string,string> $name
 * @property string|null $plate_no
 * @property string|null $model
 * @property int|null $year
 * @property string|null $colour
 * @property int $seats
 * @property string|null $transmission
 * @property string|null $fuel_type
 * @property array<int,string>|null $features
 * @property array<string,string>|null $description
 * @property string|null $address
 * @property string|null $google_maps_url
 * @property float|null $lat
 * @property float|null $lng
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
        'address',
        'google_maps_url',
        'lat',
        'lng',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'year' => 'integer',
            'seats' => 'integer',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
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

    public function isStay(): bool
    {
        return $this->category?->isStay() ?? false;
    }

    public function kind(): string
    {
        return $this->category?->kind ?? VehicleCategory::KIND_VEHICLE;
    }

    /**
     * How the unit is shown in admin pick-lists: plate first for tuk tuks
     * (that's how staff tell them apart), just the name for stays.
     */
    public function adminLabel(): string
    {
        return $this->plate_no !== null && $this->plate_no !== ''
            ? $this->plate_no.' — '.$this->name
            : (string) $this->name;
    }

    public function hasCoordinates(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    public function hasLocation(): bool
    {
        return $this->hasCoordinates() || filled($this->google_maps_url);
    }

    /**
     * Opens the place in Google Maps: the admin's own link when there is
     * one (it may carry the business listing, reviews, photos), otherwise
     * a search for the pinned coordinates.
     */
    public function mapUrl(): ?string
    {
        if (filled($this->google_maps_url)) {
            return $this->google_maps_url;
        }

        return $this->hasCoordinates()
            ? 'https://www.google.com/maps/search/?api=1&query='.$this->lat.','.$this->lng
            : null;
    }

    /** Turn-by-turn directions from wherever the customer is now. */
    public function directionsUrl(): ?string
    {
        return $this->hasCoordinates()
            ? 'https://www.google.com/maps/dir/?api=1&destination='.$this->lat.','.$this->lng
            : null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->whereIn('category_id', VehicleCategory::query()->select('id')->where('kind', $kind));
    }
}
