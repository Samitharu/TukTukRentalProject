<?php

declare(strict_types=1);

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Availability\Models\BusinessLocation;
use Modules\Availability\Models\DeliveryZone;
use Modules\Availability\Models\VehicleReservationSlot;
use Modules\Customer\Models\Customer;
use Modules\Fleet\Models\Vehicle;
use Modules\Package\Models\Package;

/**
 * @property int $id
 * @property string $reference
 * @property int $customer_id
 * @property int $vehicle_id
 * @property int|null $package_id
 * @property int|null $business_location_id
 * @property int|null $delivery_zone_id
 * @property string $pickup_type
 * @property bool $has_international_permit
 * @property string|null $special_requests
 * @property \Illuminate\Support\Carbon $start_at
 * @property \Illuminate\Support\Carbon $end_at
 * @property string $status
 * @property array<string,mixed> $price_breakdown
 * @property float $total_amount
 * @property string $currency_code
 * @property float $deposit_amount
 * @property float $amount_paid
 * @property string|null $idempotency_key
 */
final class Booking extends Model
{
    /** @use HasFactory<\Modules\Booking\Database\Factories\BookingFactory> */
    use HasFactory;

    public const string STATUS_HOLD = 'hold';

    public const string STATUS_PENDING_PAYMENT = 'pending_payment';

    public const string STATUS_CONFIRMED = 'confirmed';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_CANCELLED = 'cancelled';

    public const string STATUS_NO_SHOW = 'no_show';

    public const string STATUS_EXPIRED = 'expired';

    /**
     * Statuses that still hold a live vehicle reservation — used by the
     * admin Gantt calendar and the conflict-check composite index.
     *
     * @var string[]
     */
    public const array ACTIVE_STATUSES = [
        self::STATUS_HOLD, self::STATUS_PENDING_PAYMENT, self::STATUS_CONFIRMED, self::STATUS_ACTIVE,
    ];

    protected $fillable = [
        'reference',
        'customer_id',
        'vehicle_id',
        'package_id',
        'business_location_id',
        'delivery_zone_id',
        'pickup_type',
        'has_international_permit',
        'special_requests',
        'start_at',
        'end_at',
        'status',
        'price_breakdown',
        'total_amount',
        'currency_code',
        'deposit_amount',
        'amount_paid',
        'locale_at_booking',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'has_international_permit' => 'boolean',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'price_breakdown' => 'array',
            'total_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function businessLocation(): BelongsTo
    {
        return $this->belongsTo(BusinessLocation::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(BookingAddon::class);
    }

    public function extraCharges(): HasMany
    {
        return $this->hasMany(BookingExtraCharge::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function reservationSlots(): MorphMany
    {
        return $this->morphMany(VehicleReservationSlot::class, 'holdable');
    }

    public function scopeOverlapping(Builder $query, int $vehicleId, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('start_at', '<=', $end)
            ->where('end_at', '>=', $start);
    }
}
