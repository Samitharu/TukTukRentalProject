<?php

declare(strict_types=1);

namespace Modules\Availability\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fleet\Models\Vehicle;

/**
 * @property int $id
 * @property int|null $vehicle_id
 * @property \Illuminate\Support\Carbon $starts_on
 * @property \Illuminate\Support\Carbon $ends_on
 * @property string|null $reason
 */
final class AvailabilityBlackout extends Model
{
    protected $fillable = [
        'vehicle_id',
        'starts_on',
        'ends_on',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            // Explicit Y-m-d format, not the bare 'date' cast: the bare cast
            // still serializes through the connection's full datetime
            // format on write, which MySQL's DATE column silently truncates
            // but SQLite (used in tests) stores verbatim — breaking string
            // range comparisons like whereBetween. See AvailabilityServiceTest.
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Blackouts overlapping [$start, $end], for a specific vehicle OR
     * business-wide (vehicle_id null) — standard overlap rule: existing.start
     * <= new.end AND existing.end >= new.start.
     */
    public function scopeOverlapping(Builder $query, ?int $vehicleId, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('vehicle_id')->when($vehicleId, fn ($q2) => $q2->orWhere('vehicle_id', $vehicleId)))
            // DATE columns: compare as 'Y-m-d' — a 'Y-m-d H:i:s' string
            // misorders against them on drivers that compare as text.
            ->where('starts_on', '<=', $end->format('Y-m-d'))
            ->where('ends_on', '>=', $start->format('Y-m-d'));
    }
}
