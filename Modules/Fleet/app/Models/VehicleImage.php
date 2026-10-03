<?php

declare(strict_types=1);

namespace Modules\Fleet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vehicle_id
 * @property string $path
 * @property bool $is_primary
 * @property int $sort_order
 */
final class VehicleImage extends Model
{
    protected $fillable = [
        'vehicle_id',
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

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
