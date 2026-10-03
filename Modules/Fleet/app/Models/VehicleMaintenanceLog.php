<?php

declare(strict_types=1);

namespace Modules\Fleet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vehicle_id
 * @property string $type
 * @property string|null $description
 * @property float|null $cost
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 */
final class VehicleMaintenanceLog extends Model
{
    protected $fillable = [
        'vehicle_id',
        'type',
        'description',
        'cost',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cost' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
