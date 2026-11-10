<?php

declare(strict_types=1);

namespace Modules\Package\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Localization\Support\HasTranslatableSlug;
use Modules\Pricing\Support\Money;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $kind
 * @property int|null $product_category_id
 * @property array<string,string> $name
 * @property array<string,string>|null $description
 * @property array<string,array<int,string>>|null $inclusions
 * @property array<string,array<int,string>>|null $exclusions
 * @property string $pricing_model
 * @property int $min_days
 * @property int|null $max_days
 * @property int|null $min_hours
 * @property int|null $max_hours
 * @property int|null $included_km
 * @property bool $included_km_per_day
 * @property float|null $extra_km_rate
 * @property float|null $extra_km_rate_eur
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

    /** Rate × hours; the tuk tuk is reserved for the whole day. Tiers count hours. */
    public const string MODEL_PER_HOUR = 'per_hour';

    /** Rate × people (activities). Tiers count people. */
    public const string MODEL_PER_PERSON = 'per_person';

    /** A tuk tuk rental: booked by the day, picked up or delivered. */
    public const string KIND_VEHICLE = VehicleCategory::KIND_VEHICLE;

    /** A cabana/room: booked by the night. */
    public const string KIND_STAY = VehicleCategory::KIND_STAY;

    /**
     * Surfing, kitesurfing…: shown with prices, booked by message for now.
     * No unit is ever assigned, so these never enter the booking flow.
     */
    public const string KIND_ACTIVITY = 'activity';

    /**
     * Airport pickup / drop-off: one trip at a set time, reserving a
     * transfer car/van for that time slot only (start + the destination
     * area's duration) — see AvailabilityService::isTimeRangeFree().
     */
    public const string KIND_TRANSFER = VehicleCategory::KIND_TRANSFER;

    /** @var string[] */
    public const array KINDS = [self::KIND_VEHICLE, self::KIND_STAY, self::KIND_ACTIVITY, self::KIND_TRANSFER];

    /**
     * Which pricing models make sense for each booking style. Enforced by
     * the admin package form (see PackageKindRules).
     *
     * @var array<string, string[]>
     */
    public const array PRICING_MODELS_BY_KIND = [
        self::KIND_VEHICLE => [self::MODEL_TIERED, self::MODEL_PER_DAY, self::MODEL_PER_WEEK, self::MODEL_PER_MONTH, self::MODEL_FIXED_BUNDLE, self::MODEL_PER_HOUR],
        self::KIND_STAY => [self::MODEL_TIERED, self::MODEL_PER_DAY, self::MODEL_FIXED_BUNDLE],
        self::KIND_ACTIVITY => [self::MODEL_PER_PERSON, self::MODEL_FIXED_BUNDLE],
        // The package price is per trip (0 or a vehicle surcharge); the
        // destination area adds its own price on top.
        self::KIND_TRANSFER => [self::MODEL_FIXED_BUNDLE],
    ];

    public array $translatable = ['name', 'description', 'inclusions', 'exclusions', 'cancellation_policy'];

    /** Mirrors the column default, so an unsaved/unrefreshed model has it too. */
    protected $attributes = [
        'kind' => self::KIND_VEHICLE,
        'included_km_per_day' => true,
        'group_pricing' => false,
        'requires_licence_documents' => false,
    ];

    /** Pricing models that can be priced by group size instead. */
    public const array GROUP_PRICING_MODELS = [self::MODEL_PER_HOUR, self::MODEL_PER_DAY, self::MODEL_FIXED_BUNDLE];

    protected $fillable = [
        'kind',
        'product_category_id',
        'name',
        'description',
        'inclusions',
        'exclusions',
        'pricing_model',
        'group_pricing',
        'requires_licence_documents',
        'min_days',
        'max_days',
        'min_hours',
        'max_hours',
        'included_km',
        'included_km_per_day',
        'extra_km_rate',
        'extra_km_rate_eur',
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
            'group_pricing' => 'boolean',
            'requires_licence_documents' => 'boolean',
            'min_days' => 'integer',
            'max_days' => 'integer',
            'min_hours' => 'integer',
            'max_hours' => 'integer',
            'included_km' => 'integer',
            'included_km_per_day' => 'boolean',
            'extra_km_rate' => 'decimal:2',
            'extra_km_rate_eur' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'deposit_is_percent' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            // Explicit Y-m-d format — see VehicleReservationSlot::casts() for why.
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $package): void {
            // The category decides how the package is booked.
            if ($package->product_category_id !== null && $package->isDirty('product_category_id')) {
                $package->kind = ProductCategory::query()->whereKey($package->product_category_id)->value('kind') ?? $package->kind;
            }

            // Only something the customer drives needs their licence.
            if ($package->kind !== self::KIND_VEHICLE) {
                $package->requires_licence_documents = false;
            }

            // Hourly rentals are always a single day: that is what keeps
            // them on the day-granular reservation slots unchanged.
            if ($package->pricing_model === self::MODEL_PER_HOUR) {
                $package->min_days = 1;
                $package->max_days = 1;
            }
        });

        static::saved(fn (self $package) => $package->syncSlugs());
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
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

    public function offers(): HasMany
    {
        return $this->hasMany(PackageOffer::class);
    }

    /**
     * The offers a customer can take right now: the package they would add
     * is on sale and booked online.
     */
    public function liveOffers(): HasMany
    {
        return $this->offers()
            ->with(['offeredPackage.pricingTiers', 'offeredPackage.images'])
            ->whereHas('offeredPackage', fn (Builder $package) => $package->active()->bookable()->currentlyValid()->inVisibleCategory())
            ->orderBy('id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PackageOffer>
     */
    public function availableOffers(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->liveOffers()->get();
    }

    /**
     * The same offers seen from the other side: packages that become cheaper
     * when they are booked together with this one.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PackageOffer>
     */
    public function offersOnOtherPackages(): \Illuminate\Database\Eloquent\Collection
    {
        return PackageOffer::query()
            ->where('offered_package_id', $this->id)
            ->with(['package.pricingTiers', 'package.images'])
            ->whereHas('package', fn (Builder $package) => $package->active()->bookable()->currentlyValid()->inVisibleCategory())
            ->orderBy('id')
            ->get();
    }

    public function images(): HasMany
    {
        return $this->hasMany(PackageImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): ?PackageImage
    {
        return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
    }

    /**
     * The cover first, then the rest in upload order.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PackageImage>
     */
    public function galleryImages(): \Illuminate\Database\Eloquent\Collection
    {
        $cover = $this->primaryImage();

        return $this->images->sortBy(fn (PackageImage $image) => $image->id === $cover?->id ? 0 : 1)->values();
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'package_vehicles');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(VehicleCategory::class, 'package_categories', 'package_id', 'category_id');
    }

    /**
     * A stay package books a cabana/room by the night; a vehicle package
     * books a tuk tuk by the day. Same values as VehicleCategory::KINDS.
     */
    public function isStay(): bool
    {
        return $this->kind === self::KIND_STAY;
    }

    public function isActivity(): bool
    {
        return $this->kind === self::KIND_ACTIVITY;
    }

    public function isTransfer(): bool
    {
        return $this->kind === self::KIND_TRANSFER;
    }

    public function isHourly(): bool
    {
        return $this->pricing_model === self::MODEL_PER_HOUR;
    }

    /**
     * Priced by group size, e.g. per boat: 3–5 people $10, 6–7 people $18.
     * Its price rows count people; the price found is then charged the
     * package's usual way (per hour, per day, or once).
     */
    public function isGroupPriced(): bool
    {
        return $this->group_pricing
            && ! $this->isStay()
            && ! $this->isTransfer()
            && in_array($this->pricing_model, self::GROUP_PRICING_MODELS, true);
    }

    /**
     * Whether an airport pickup has a price of its own. One that doesn't is
     * priced entirely by the destination area, so it can't be booked
     * without one: it would come to nothing.
     */
    public function needsTransferArea(): bool
    {
        return $this->isTransfer() && (float) ($this->pricingTiers->first()?->price ?? 0) <= 0;
    }

    /** Whether a price row covers a group of $people. */
    public function hasPriceForPeople(int $people): bool
    {
        return $people > 0 && $this->pricingTiers->contains(fn (PackagePricingTier $tier) => $tier->covers($people));
    }

    /**
     * Every group size that has a price, smallest first, for the "number of
     * people" choice.
     *
     * @return int[]
     */
    public function groupSizes(): array
    {
        return $this->pricingTiers
            ->flatMap(fn (PackagePricingTier $tier) => range($tier->min_days, $tier->max_days ?? $tier->min_days))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** An activity, or its line's vehicles come from partners: always bookable, reserves no unit. */
    public function hasUnlimitedUnits(): bool
    {
        return $this->isActivity() || ($this->productCategory?->unlimited_units ?? false);
    }

    /**
     * Whether a price exists for this many days or nights. When its prices
     * stop short of that length PricingService finds none and the bill comes
     * to nothing, so such a booking must not be accepted. Hourly and
     * per-person prices count hours and people, which are checked apart.
     */
    public function hasPriceForDays(int $days): bool
    {
        if ($this->isHourly() || $this->pricing_model === self::MODEL_PER_PERSON || $this->isGroupPriced()) {
            return true;
        }

        return $this->pricingTiers->isEmpty()
            || $this->pricingTiers->contains(fn (PackagePricingTier $tier) => $tier->min_days <= $days && ($tier->max_days === null || $tier->max_days >= $days));
    }

    /** Whether the package goes through the online booking flow (Phase 1: not activities). */
    public function isBookableOnline(): bool
    {
        return ! $this->isActivity();
    }

    /** A km allowance with a per-km charge beyond it. */
    public function hasKmAllowance(): bool
    {
        return $this->included_km !== null && $this->extra_km_rate !== null;
    }

    public function displayExtraKmRate(): string
    {
        return Money::dual($this->extra_km_rate, $this->extra_km_rate_eur);
    }

    /** Total km included in a booking of $days days, or null for unlimited. */
    public function includedKmFor(int $days): ?int
    {
        if ($this->included_km === null) {
            return null;
        }

        return $this->included_km_per_day ? $this->included_km * max($days, 1) : $this->included_km;
    }

    /** Translation key for the price suffix: "/ day", "/ hour", "total"… */
    public function priceSuffixKey(): string
    {
        return match ($this->pricing_model) {
            self::MODEL_PER_HOUR => 'core::front.packages_per_hour',
            self::MODEL_PER_PERSON => 'core::front.packages_per_person',
            self::MODEL_PER_WEEK => 'core::front.packages_per_week',
            self::MODEL_PER_MONTH => 'core::front.packages_per_month',
            self::MODEL_FIXED_BUNDLE => $this->isTransfer() ? 'core::front.packages_per_trip' : 'core::front.packages_total',
            default => $this->isStay() ? 'core::front.packages_per_night' : 'core::front.packages_per_day',
        };
    }

    /**
     * The tier behind the "from" price: for an hourly package, the one that
     * covers its minimum hours.
     */
    public function fromTier(): ?PackagePricingTier
    {
        // Rows count people: the smallest group's price.
        if ($this->isGroupPriced()) {
            return $this->pricingTiers->first();
        }

        if ($this->isHourly()) {
            $minimum = max((int) $this->min_hours, 1);
            $covering = $this->pricingTiers->first(fn (PackagePricingTier $tier) => $tier->covers($minimum));

            if ($covering !== null) {
                return $covering;
            }
        }

        return $this->pricingTiers->first();
    }

    /**
     * The fewest hours a booking at this tier can be, when that is more than
     * one; prices are then shown for those hours, not per hour. Null for
     * packages not priced by the hour.
     */
    public function pricedHours(PackagePricingTier $tier): ?int
    {
        if (! $this->isHourly()) {
            return null;
        }

        // A group-priced row counts people, not hours.
        $hours = max((int) $this->min_hours, $this->isGroupPriced() ? 1 : $tier->min_days, 1);

        return $hours > 1 ? $hours : null;
    }

    /** "$12.00 / €10.00" for a 2-hour minimum at $6 an hour; the tier's own price otherwise. */
    public function tierPrice(PackagePricingTier $tier): string
    {
        $hours = $this->pricedHours($tier);

        return $hours === null ? $tier->displayPrice() : $tier->displayPriceFor($hours);
    }

    /** "3–5 people" for a row of a group-priced package. */
    public function groupSizeLabel(PackagePricingTier $tier): string
    {
        $range = $tier->max_days === null || $tier->max_days === $tier->min_days
            ? (string) $tier->min_days
            : $tier->min_days.'–'.$tier->max_days;

        return $range.' '.__('core::front.packages_unit_people');
    }

    /** What tierPrice() buys: "for 2 hours", or the usual "/ day", "/ hour"… */
    public function tierPriceSuffix(PackagePricingTier $tier): string
    {
        $hours = $this->pricedHours($tier);

        return $hours === null
            ? __($this->priceSuffixKey())
            : __('core::front.packages_for_hours', ['hours' => $hours]);
    }

    /** What one tier price buys: "day", "week", "hour"…; null when it's the whole package's price. */
    public function tierPriceUnit(): ?string
    {
        return match ($this->pricing_model) {
            self::MODEL_FIXED_BUNDLE => null,
            self::MODEL_PER_WEEK => 'week',
            self::MODEL_PER_MONTH => 'month',
            self::MODEL_PER_HOUR => 'hour',
            self::MODEL_PER_PERSON => 'person',
            default => $this->isStay() ? 'night' : 'day',
        };
    }

    /** What a pricing tier's from/to numbers count: days, nights, hours or people. */
    public function tierUnit(): string
    {
        return match (true) {
            $this->isGroupPriced(), $this->pricing_model === self::MODEL_PER_PERSON => 'people',
            $this->isHourly() => 'hours',
            $this->isStay() => 'nights',
            default => 'days',
        };
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    /**
     * Not in a category the admin has hidden: hiding a category takes its
     * packages off the website and out of the booking flow.
     */
    public function scopeInVisibleCategory(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereNull('product_category_id')
            ->orWhereHas('productCategory', fn ($category) => $category->where('is_active', true)));
    }

    /** Packages the online booking flow (and admin manual bookings) can book. */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('kind', '!=', self::KIND_ACTIVITY);
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
     * categories, or (if neither is restricted) every active unit of the
     * package's kind and product line — an unrestricted tuk tuk package
     * must never auto-assign a cabana or a scooter, nor a stay package a
     * tuk tuk. Unit types not yet assigned to any line count for every
     * line of their kind.
     */
    public function eligibleVehicleIds(): array
    {
        if ($this->hasUnlimitedUnits()) {
            return [];
        }

        if ($this->isRestrictedToSpecificVehicles()) {
            // Same active-only rule as the two branches below: a vehicle
            // attached to the package but since retired or sent to the
            // workshop must not be auto-assigned to a customer.
            return $this->vehicles()->where('vehicles.status', Vehicle::STATUS_ACTIVE)->pluck('vehicles.id')->all();
        }

        if ($this->isRestrictedToCategories()) {
            return Vehicle::query()->active()
                ->whereIn('category_id', $this->categories()->pluck('vehicle_categories.id'))
                ->pluck('id')
                ->all();
        }

        return Vehicle::query()->active()
            ->whereIn('category_id', $this->unitTypesOfOwnLine()->select('id'))
            ->pluck('id')
            ->all();
    }

    /**
     * Unit types an unrestricted package may assign from: of its kind, in
     * its product line or in no line at all.
     *
     * @return Builder<VehicleCategory>
     */
    public function unitTypesOfOwnLine(): Builder
    {
        return VehicleCategory::query()
            ->where('kind', $this->kind)
            ->when($this->product_category_id !== null, fn ($q) => $q->where(fn ($line) => $line
                ->whereNull('product_category_id')
                ->orWhere('product_category_id', $this->product_category_id)));
    }

    /**
     * eligibleVehicleIds() without per-package queries, for listing many
     * packages at once: the same three rules, evaluated against eager-loaded
     * `vehicles` and `categories` and a pre-fetched active fleet — which
     * the caller must already have narrowed to this package's kind.
     *
     * @param  array<int, int>  $activeFleet  active vehicle id => category id
     * @param  array<int, int|null>  $unitTypeLines  unit type (category) id => its product line id
     * @return int[]
     */
    public function eligibleVehicleIdsAmong(array $activeFleet, array $unitTypeLines = []): array
    {
        if ($this->vehicles->isNotEmpty()) {
            return $this->vehicles
                ->where('status', Vehicle::STATUS_ACTIVE)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        if ($this->categories->isNotEmpty()) {
            $categoryIds = $this->categories->pluck('id')->map(fn ($id) => (int) $id)->all();

            return array_keys(array_filter($activeFleet, fn ($categoryId) => in_array((int) $categoryId, $categoryIds, true)));
        }

        if ($this->product_category_id === null) {
            return array_keys($activeFleet);
        }

        return array_keys(array_filter($activeFleet, function ($categoryId) use ($unitTypeLines): bool {
            $line = $unitTypeLines[(int) $categoryId] ?? null;

            return $line === null || (int) $line === (int) $this->product_category_id;
        }));
    }
}
