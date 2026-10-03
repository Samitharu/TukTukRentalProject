<?php

declare(strict_types=1);

namespace Modules\Fleet\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Localization\Support\HasTranslatableSlug;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
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

    public array $translatable = ['name', 'description'];

    protected $fillable = [
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
