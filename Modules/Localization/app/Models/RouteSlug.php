<?php

declare(strict_types=1);

namespace Modules\Localization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The single table every translatable, routable model resolves its
 * per-locale slug through. See docs/03-database-schema.md (Localization).
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property string $locale
 * @property string $slug
 */
final class RouteSlug extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'locale',
        'slug',
    ];

    public function routable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'model_type', 'model_id');
    }
}
