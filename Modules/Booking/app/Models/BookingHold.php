<?php

declare(strict_types=1);

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Availability\Models\VehicleReservationSlot;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Package\Models\Package;

/**
 * A temporary reservation created at the booking review step, expiring
 * after `config('booking.hold_minutes')` (brief §6 point 4). Converts to a
 * `Booking` via `BookingService::confirmHold()`, which re-points (not
 * re-inserts) its `vehicle_reservation_slots` rows.
 *
 * @property int $id
 * @property string $hold_key
 * @property int|null $vehicle_id
 * @property int|null $category_id
 * @property int|null $package_id
 * @property \Illuminate\Support\Carbon $start_at
 * @property \Illuminate\Support\Carbon $end_at
 * @property \Illuminate\Support\Carbon $expires_at
 * @property string $status
 */
final class BookingHold extends Model
{
    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_CONVERTED = 'converted';

    public const string STATUS_EXPIRED = 'expired';

    public const string STATUS_RELEASED = 'released';

    protected $fillable = [
        'hold_key',
        'vehicle_id',
        'category_id',
        'package_id',
        'customer_session_id',
        'start_at',
        'end_at',
        'expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'category_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function reservationSlots(): MorphMany
    {
        return $this->morphMany(VehicleReservationSlot::class, 'holdable');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function scopeExpiredAndActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->where('expires_at', '<', now());
    }
}
