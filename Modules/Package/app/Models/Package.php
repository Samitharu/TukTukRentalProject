<?php

declare(strict_types=1);

namespace Modules\Package\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Localization\Support\HasTranslatableSlug;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property array<string,string> $name
 * @property array<string,string>|null $description
 * @property array<string,array<int,string>>|null $inclusions
 * @property array<string,array<int,string>>|null $exclusions
 * @property string $pricing_model
 * @property int $min_days
 * @property int|null $max_days
 * @property int|null $included_km
 * @property float|null $deposit_amount
 * @property bool $deposit_is_percent
 * @property bool $is_active
 * @property bool $is_featured
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_until
 */
final class Package extends Model
{
    /** @use HasFactory<\Modules\Package\Database\Factories\PackageFactory> */
    use HasFactory;
    use HasTranslatableSlug;
    use HasTranslations;
    use SoftDeletes;

    public const string MODEL_PER_DAY = 'per_day';

    public const string MODEL_PER_WEEK = 'per_week';

    public const string MODEL_PER_MONTH = 'per_month';

    public const string MODEL_FIXED_BUNDLE = 'fixed_bundle';

    public const string MODEL_TIERED = 'tiered';

    public array $translatable = ['name', 'description', 'inclusions', 'exclusions', 'cancellation_policy'];

    protected $fillable = [
        'name',
        'description',
        'inclusions',
        'exclusions',
        'pricing_model',
        'min_days',
        'max_days',
        'included_km',
        'deposit_amount',
        'deposit_is_percent',
        'cancellation_policy',
        'is_active',
        'is_featured',
        'sort_order',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'min_days' => 'integer',
            'max_days' => 'integer',
            'included_km' => 'integer',
            'deposit_amount' => 'decimal:2',
            'deposit_is_percent' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            // Explicit Y-m-d format — see AvailabilityBlackout::casts() for why.
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $package) => $package->syncSlugs());
    }

    public function pricingTiers(): HasMany
    {
        return $this->hasMany(PackagePricingTier::class)->orderBy('min_days');
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(PackageSeason::class);
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'package_addons')->withPivot('is_included');
    }

    public function images(): HasMany
    {
        return $this->hasMany(PackageImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): ?PackageImage
    {
        return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'package_vehicles');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(VehicleCategory::class, 'package_categories', 'package_id', 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrentlyValid(Builder $query, ?\DateTimeInterface $on = null): Builder
    {
        $on ??= now();

        return $query
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $on))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $on));
    }

    public function isRestrictedToSpecificVehicles(): bool
    {
        return $this->vehicles()->exists();
    }

    public function isRestrictedToCategories(): bool
    {
        return $this->categories()->exists();
    }

    /**
     * Which specific vehicles this package can be booked with — either the
     * explicit vehicle list, or every active vehicle in the allowed
     * categories, or (if neither is restricted) the whole active fleet.
     */
    public function eligibleVehicleIds(): array
    {
        if ($this->isRestrictedToSpecificVehicles()) {
            return $this->vehicles()->pluck('vehicles.id')->all();
        }

        if ($this->isRestrictedToCategories()) {
            return Vehicle::query()->active()
                ->whereIn('category_id', $this->categories()->pluck('vehicle_categories.id'))
                ->pluck('id')
                ->all();
        }

        return Vehicle::query()->active()->pluck('id')->all();
    }
}
