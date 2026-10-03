<?php

declare(strict_types=1);

namespace Modules\Localization\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $model_type
 * @property int $model_id
 * @property string $locale
 * @property string $field
 */
final class MissingTranslationLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'missing_translations_log';

    protected $fillable = [
        'model_type',
        'model_id',
        'locale',
        'field',
    ];

    /**
     * Idempotent: records a missing-translation occurrence without spamming
     * duplicate rows for the same (model, locale, field) combination.
     */
    public static function record(string $modelType, int $modelId, string $locale, string $field): void
    {
        self::query()->firstOrCreate([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'locale' => $locale,
            'field' => $field,
        ], [
            'created_at' => now(),
        ]);
    }
}
