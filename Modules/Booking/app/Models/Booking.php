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
 * @property int|null $included_km
 * @property float|null $extra_km_rate
 * @property int|null $odometer_start
 * @property int|null $odometer_end
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
        'included_km',
        'extra_km_rate',
        'odometer_start',
        'odometer_end',
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
            'included_km' => 'integer',
            'extra_km_rate' => 'decimal:2',
            'odometer_start' => 'integer',
            'odometer_end' => 'integer',
        ];
    }

    // Customer, Vehicle and Package soft-delete, and the bookings FKs can't
    // stop that (the row stays). A booking is a historical record, so it
    // keeps resolving what was actually booked even after it's deleted —
    // otherwise every page that touches $booking->vehicle hits null.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class)->withTrashed();
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

    /**
     * A cabana/room booking rather than a tuk tuk rental.
     *
     * Stays are stored in the same day-granular shape as rentals: start_at
     * is the check-in day and end_at the LAST NIGHT (check-out minus one) —
     * exactly the days reserved in vehicle_reservation_slots, so the next
     * guest can check in on this guest's check-out day. Show the customer
     * checkOutDate(), never end_at directly.
     */
    public function isStay(): bool
    {
        return $this->vehicle?->isStay() ?? false;
    }

    /** Return day for a rental; the morning after the last night for a stay. */
    public function checkOutDate(): \Illuminate\Support\Carbon
    {
        return $this->isStay() ? $this->end_at->copy()->addDay() : $this->end_at;
    }

    /**
     * Hours booked, for an hourly package (a single-day rental whose
     * start_at/end_at carry real times); null for every other booking.
     * Read from the price snapshot, so a later package edit can't change it.
     */
    public function hours(): ?int
    {
        $hours = $this->price_breakdown['hours'] ?? null;

        return $hours !== null ? (int) $hours : null;
    }

    public function isHourly(): bool
    {
        return $this->hours() !== null;
    }

    /**
     * A rental's period for display: "10 Oct 2026 → 13 Oct 2026", or for
     * an hourly rental "10 Oct 2026, 09:00–13:00 (4 hours)". Stays show
     * check-in/check-out instead (see checkOutDate()).
     */
    public function rentalPeriod(string $dateFormat = 'd M Y'): string
    {
        if ($this->isHourly()) {
            return $this->start_at->format($dateFormat).', '.$this->start_at->format('H:i').'–'.$this->end_at->format('H:i')
                .' ('.trans_choice('core::front.booking_hours_count', (int) $this->hours(), ['count' => $this->hours()]).')';
        }

        return $this->start_at->format($dateFormat).' → '.$this->end_at->format($dateFormat);
    }

    /** A km allowance with a per-km charge beyond it was part of the booking. */
    public function hasKmAllowance(): bool
    {
        return $this->included_km !== null && $this->extra_km_rate !== null;
    }

    public function kmDriven(): ?int
    {
        return $this->odometer_start !== null && $this->odometer_end !== null
            ? max($this->odometer_end - $this->odometer_start, 0)
            : null;
    }

    public function extraKm(): int
    {
        $driven = $this->kmDriven();

        return $driven !== null && $this->included_km !== null ? max($driven - $this->included_km, 0) : 0;
    }

    /** Rental days (pickup and return inclusive) or nights stayed. */
    public function lengthInDays(): int
    {
        return (int) $this->start_at->copy()->startOfDay()->diffInDays($this->end_at->copy()->startOfDay()) + 1;
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
