<?php

declare(strict_types=1);

namespace Modules\Package\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $package_id
 * @property string $path
 * @property bool $is_primary
 * @property int $sort_order
 */
final class PackageImage extends Model
{
    protected $fillable = [
        'package_id',
        'path',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
