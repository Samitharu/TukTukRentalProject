<?php

declare(strict_types=1);

namespace Modules\Package\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Localization\Support\HasTranslatableSlug;
use Spatie\Translatable\HasTranslations;

/**
 * A line of business the admin manages its own packages under — Tuk Tuk
 * Rental, Stays, Surfing, Kitesurfing, and whatever comes next. Not to be
 * confused with Fleet's VehicleCategory ("unit types": Classic Tuk Tuk,
 * Beach Cabana…), which groups the physical units a booking is assigned to.
 *
 * `kind` is the booking style (Package::KINDS) and is copied onto every
 * package in the category, which is what the booking flow keys off.
 *
 * @property int $id
 * @property string $kind
 * @property array<string,string> $name
 * @property array<string,string>|null $description
 * @property string|null $image_path
 * @property int $sort_order
 * @property bool $is_active
 */
final class ProductCategory extends Model
{
    /** @use HasFactory<\Modules\Package\Database\Factories\ProductCategoryFactory> */
    use HasFactory;
    use HasTranslatableSlug;
    use HasTranslations;

    public array $translatable = ['name', 'description'];

    /** Mirrors the column default, so an unsaved/unrefreshed model has it too. */
    protected $attributes = [
        'kind' => Package::KIND_VEHICLE,
    ];

    protected $fillable = [
        'kind',
        'name',
        'description',
        'image_path',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $category) => $category->syncSlugs());
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isActivity(): bool
    {
        return $this->kind === Package::KIND_ACTIVITY;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path !== null ? asset('storage/'.$this->image_path) : null;
    }

    /**
     * The pricing models a package in this category may use.
     *
     * @return string[]
     */
    public function pricingModels(): array
    {
        return Package::PRICING_MODELS_BY_KIND[$this->kind] ?? [];
    }
}
