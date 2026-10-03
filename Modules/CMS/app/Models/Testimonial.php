<?php

declare(strict_types=1);

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

final class Testimonial extends Model
{
    use HasTranslations;

    public array $translatable = ['content'];

    protected $fillable = [
        'customer_name',
        'country',
        'rating',
        'content',
        'is_approved',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_approved' => 'boolean',
        ];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }
}
