<?php

declare(strict_types=1);

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Availability\Models\DeliveryZone;
use Modules\Availability\Models\TransferArea;
use Modules\Availability\Models\VehicleReservationSlot;
use Modules\Customer\Models\Customer;
use Modules\Fleet\Models\Vehicle;
use Modules\Package\Models\Package;
use Modules\Pricing\Support\Money;

/**
 * @property int $id
 * @property string $reference
 * @property int $customer_id
 * @property int|null $vehicle_id null: no registered unit (see hasNoUnit()); set only on older bookings
 * @property int|null $package_id
 * @property int|null $delivery_zone_id
 * @property string $pickup_type
 * @property string|null $transfer_direction
 * @property string|null $flight_number
 * @property string|null $transfer_address
 * @property int|null $transfer_area_id
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
 * @property float|null $extra_km_rate_eur
 * @property int|null $odometer_start
 * @property int|null $odometer_end
 * @property string|null $idempotency_key
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $phone_key
 * @property string|null $passport_key
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

    /** Airport pickup: from the airport to the customer's address. */
    public const string TRANSFER_ARRIVAL = 'arrival';

    /** Airport drop-off: from the customer's address to the airport. */
    public const string TRANSFER_DEPARTURE = 'departure';

    /** @var string[] */
    public const array TRANSFER_DIRECTIONS = [self::TRANSFER_ARRIVAL, self::TRANSFER_DEPARTURE];

    /**
     * Statuses that still hold a live vehicle reservation — used by the
     * admin Gantt calendar and the conflict-check composite index.
     *
     * @var string[]
     */
    public const array ACTIVE_STATUSES = [
        self::STATUS_HOLD, self::STATUS_PENDING_PAYMENT, self::STATUS_CONFIRMED, self::STATUS_ACTIVE,
    ];

    /**
     * Booked but not yet picked up — the statuses staff can still cancel,
     * and the ones a fake booking sits in while it blocks the fleet.
     *
     * @var string[]
     */
    public const array OPEN_STATUSES = [
        self::STATUS_HOLD, self::STATUS_PENDING_PAYMENT, self::STATUS_CONFIRMED,
    ];

    protected $fillable = [
        'reference',
        'customer_id',
        'vehicle_id',
        'package_id',
        'delivery_zone_id',
        'pickup_type',
        'transfer_direction',
        'flight_number',
        'transfer_address',
        'transfer_area_id',
        'linked_booking_id',
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
        'extra_km_rate_eur',
        'odometer_start',
        'odometer_end',
        'locale_at_booking',
        'idempotency_key',
        'ip_address',
        'user_agent',
        'phone_key',
        'passport_key',
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
            'extra_km_rate_eur' => 'decimal:2',
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


    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function transferArea(): BelongsTo
    {
        return $this->belongsTo(TransferArea::class);
    }

    /**
     * The first booking of a checkout points at the booking that earned its
     * offer saving (or the first one added); the others point at the first.
     */
    public function linkedBooking(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_booking_id');
    }

    /**
     * Everything booked in the same checkout as this one. Works from any of
     * them: the one this points at, plus all that point at it or at this.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public function togetherBookings(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->linked_booking_id === null) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return self::query()
            ->with('package')
            ->where(fn ($query) => $query
                ->whereKey($this->linked_booking_id)
                ->orWhereIn('linked_booking_id', [$this->id, $this->linked_booking_id]))
            ->whereKeyNot($this->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * No registered unit: the business arranges the vehicle or stay itself,
     * so the booking reserves nothing. Only older bookings have a unit.
     */
    public function hasNoUnit(): bool
    {
        return $this->vehicle_id === null;
    }

    /** For staff: the unit on an older booking, otherwise the service booked. */
    public function serviceLabel(): string
    {
        return $this->vehicle?->adminLabel() ?? (string) ($this->package?->productCategory?->name ?? $this->package?->name ?? '—');
    }

    /** What was booked: from the vehicle, or the package when there is none. */
    public function kind(): string
    {
        return $this->vehicle?->kind() ?? $this->package?->kind ?? Package::KIND_VEHICLE;
    }

    /**
     * An airport pickup/drop-off rather than a rental or stay. start_at is
     * the pickup time and end_at when the vehicle is free again (start +
     * the area's duration) — real times, never whole days, and no
     * vehicle_reservation_slots rows (see AvailabilityService::isTimeRangeFree()).
     */
    public function isTransfer(): bool
    {
        return $this->transfer_direction !== null;
    }

    /**
     * Where a rental is collected: the business address, when one has been
     * set (config/core's "[… pending]" placeholder isn't shown to customers).
     * Stays, airport pickups and activities aren't collected from the office.
     */
    public function officeAddress(): ?string
    {
        if ($this->isStay() || $this->isTransfer() || $this->isActivity()) {
            return null;
        }

        return self::businessAddress();
    }

    /** The business's address, unless it is still config/core's "[… pending]" placeholder. */
    public static function businessAddress(): ?string
    {
        $address = config('core.business.address');

        return is_string($address) && $address !== '' && ! str_starts_with($address, '[') ? $address : null;
    }

    public function officeMapUrl(): ?string
    {
        $address = $this->officeAddress();

        return $address !== null ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address) : null;
    }

    /** "Airport → your address" or "Your address → airport", translated. */
    public function transferRouteLabel(): string
    {
        return __($this->transfer_direction === self::TRANSFER_DEPARTURE
            ? 'core::front.transfer_departure'
            : 'core::front.transfer_arrival');
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
        return $this->kind() === Package::KIND_STAY;
    }

    /** A lesson or similar: one day, no vehicle, and we confirm the time with the customer. */
    public function isActivity(): bool
    {
        return $this->kind() === Package::KIND_ACTIVITY;
    }

    /** People booked, for a package priced per person. From the price snapshot, like hours(). */
    public function people(): ?int
    {
        $people = $this->price_breakdown['people'] ?? null;

        return $people !== null ? (int) $people : null;
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
        if ($this->isTransfer()) {
            return $this->start_at->format($dateFormat).', '.$this->start_at->format('H:i');
        }

        if ($this->isActivity()) {
            return $this->start_at->format($dateFormat).($this->people() !== null
                ? ' ('.trans_choice('core::front.booking_people_count', $this->people(), ['count' => $this->people()]).')'
                : '');
        }

        if ($this->isHourly()) {
            return $this->start_at->format($dateFormat).', '.$this->start_at->format('H:i').'–'.$this->end_at->format('H:i')
                .' ('.trans_choice('core::front.booking_hours_count', (int) $this->hours(), ['count' => $this->hours()]).')';
        }

        return $this->start_at->format($dateFormat).' → '.$this->end_at->format($dateFormat);
    }

    /** The EUR total frozen at booking time; null on older bookings or when a price had no EUR. */
    public function totalEur(): ?float
    {
        $total = $this->price_breakdown['eur']['total'] ?? null;

        return $total !== null ? (float) $total : null;
    }

    /** What the customer sees: "$25.00 / €21.50". */
    public function displayTotal(): string
    {
        return Money::dual($this->total_amount, $this->totalEur());
    }

    /** What staff see: "$25.00 (LKR 7,500) / €21.50 (LKR 7,310)". */
    public function adminTotal(): string
    {
        return Money::admin($this->total_amount, $this->totalEur());
    }

    /** The package offer's saving frozen on this booking: "-$3.00 / -€2.50", or null. */
    public function displayOfferDiscount(): ?string
    {
        $discount = (float) ($this->price_breakdown['offer_discount'] ?? 0);
        $discountEur = $this->price_breakdown['eur']['offer_discount'] ?? null;

        return $discount > 0 ? Money::dual(-$discount, $discountEur !== null ? -(float) $discountEur : null) : null;
    }

    /** The whole checkout's total as customers see it: "$72.00 / €65.00". */
    public function displayTotalWithLinked(): string
    {
        return Money::dual(...$this->totalsWithLinked());
    }

    /** The whole checkout's total as staff see it, with LKR. */
    public function adminTotalWithLinked(): string
    {
        return Money::admin(...$this->totalsWithLinked());
    }

    /** Still to pay on the whole checkout. As displayBalance(): EUR only while nothing is paid. */
    public function displayBalanceWithLinked(): string
    {
        $bookings = $this->owedInCheckout();
        $paid = (float) $bookings->sum('amount_paid');
        $eur = $bookings->map(fn (self $booking) => $booking->totalEur());

        return Money::dual(
            (float) $bookings->sum(fn (self $booking) => max(0, (float) $booking->total_amount - (float) $booking->amount_paid)),
            $paid === 0.0 && ! $eur->containsStrict(null) ? (float) $eur->sum() : null,
        );
    }

    /**
     * The whole checkout in booking order, this one included.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public function checkoutBookings(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->togetherBookings()->push($this)->sortBy('id')->values();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    private function owedInCheckout(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->checkoutBookings()->reject(fn (self $booking) => $booking->status === self::STATUS_CANCELLED);
    }

    /**
     * Those still owed in this checkout; EUR only if all have it.
     *
     * @return array{0: float, 1: float|null}
     */
    private function totalsWithLinked(): array
    {
        $bookings = $this->owedInCheckout();
        $eur = $bookings->map(fn (self $booking) => $booking->totalEur());

        return [
            (float) $bookings->sum('total_amount'),
            $eur->containsStrict(null) ? null : (float) $eur->sum(),
        ];
    }

    /** Still to pay. Payments are recorded in USD, so EUR only while nothing is paid. */
    public function displayBalance(): string
    {
        $paid = (float) $this->amount_paid;

        return Money::dual(max(0, (float) $this->total_amount - $paid), $paid === 0.0 ? $this->totalEur() : null);
    }

    public function adminTotalWithExtras(): string
    {
        $eur = $this->totalEur();

        return Money::admin(
            (float) $this->total_amount + (float) $this->extraCharges->sum('amount'),
            $eur !== null && $this->extraCharges->every(fn (BookingExtraCharge $charge) => $charge->amount_eur !== null)
                ? $eur + (float) $this->extraCharges->sum('amount_eur')
                : null,
        );
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
