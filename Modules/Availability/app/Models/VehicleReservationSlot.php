<?php

declare(strict_types=1);

namespace Modules\Availability\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Fleet\Models\Vehicle;

/**
 * One row per vehicle per reserved calendar day. The UNIQUE(vehicle_id,
 * slot_date) database constraint — not this model — is what actually makes
 * double-booking impossible; see docs/03-database-schema.md. Written to by
 * the Booking module (Phase 4) via AvailabilityService::reserveSlots().
 *
 * @property int $id
 * @property int $vehicle_id
 * @property \Illuminate\Support\Carbon $slot_date
 * @property string $holdable_type
 * @property int $holdable_id
 */
final class VehicleReservationSlot extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'vehicle_id',
        'slot_date',
        'holdable_type',
        'holdable_id',
    ];

    protected function casts(): array
    {
        return [
            // Explicit Y-m-d format, not the bare 'date' cast: the bare cast
            // still serializes through the connection's full datetime
            // format on write, which MySQL's DATE column silently truncates
            // but SQLite (used in tests) stores verbatim — breaking string
            // range comparisons like whereBetween.
            'slot_date' => 'date:Y-m-d',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function holdable(): MorphTo
    {
        return $this->morphTo();
    }
}
