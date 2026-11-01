<?php

declare(strict_types=1);

namespace Modules\Fleet\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Localization\Support\HasTranslatableSlug;
use Modules\Package\Models\ProductCategory;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $kind
 * @property int|null $product_category_id
 * @property array<string,string> $name
 * @property array<string,string>|null $description
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_active
 */
final class VehicleCategory extends Model
{
    /** @use HasFactory<\Modules\Fleet\Database\Factories\VehicleCategoryFactory> */
    use HasFactory;
    use HasTranslatableSlug;
    use HasTranslations;

    /** Tuk tuks: rented by the day, picked up or delivered. */
    public const string KIND_VEHICLE = 'vehicle';

    /** Cabanas and rooms: booked by the night at their own location. */
    public const string KIND_STAY = 'stay';

    /** Airport cars/vans with a driver: booked by time slot, not by the day. */
    public const string KIND_TRANSFER = 'transfer';

    /** @var string[] */
    public const array KINDS = [self::KIND_VEHICLE, self::KIND_STAY, self::KIND_TRANSFER];

    public array $translatable = ['name', 'description'];

    /** Mirrors the column default, so an unsaved/unrefreshed model has it too. */
    protected $attributes = [
        'kind' => self::KIND_VEHICLE,
    ];

    protected $fillable = [
        'kind',
        'product_category_id',
        'name',
        'description',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $category) => $category->syncSlugs());
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'category_id');
    }

    /** The product line (Tuk Tuk, Vehicle Rent…) this unit type belongs to. */
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function isStay(): bool
    {
        return $this->kind === self::KIND_STAY;
    }

    public function isTransfer(): bool
    {
        return $this->kind === self::KIND_TRANSFER;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }
}
