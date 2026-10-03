<?php

declare(strict_types=1);

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * @property array<string,string> $question
 * @property array<string,string> $answer
 * @property string|null $category
 */
final class Faq extends Model
{
    /** @use HasFactory<\Modules\CMS\Database\Factories\FaqFactory> */
    use HasFactory, HasTranslations;

    public array $translatable = ['question', 'answer'];

    protected $fillable = [
        'question',
        'answer',
        'category',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
