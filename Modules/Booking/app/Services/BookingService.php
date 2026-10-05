<?php

declare(strict_types=1);

namespace Modules\Booking\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Modules\Availability\Exceptions\SlotConflictException;
use Modules\Availability\Services\AvailabilityService;
use Modules\Booking\Events\BookingPlaced;
use Modules\Booking\Exceptions\CouponUnavailableException;
use Modules\Booking\Exceptions\HoldExpiredException;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingExtraCharge;
use Modules\Booking\Models\BookingHold;
use Modules\Booking\Models\BookingStatusHistory;
use Modules\Booking\Support\BookingReference;
use Modules\Booking\Support\BookingStateMachine;
use Modules\Customer\Services\CustomerService;
use Modules\Fleet\Models\Vehicle;
use Modules\Package\Models\Addon;
use Modules\Package\Models\Package;
use Modules\Pricing\DataObjects\PriceBreakdown;
use Modules\Pricing\Models\Coupon;

/**
 * The single, authoritative surface for every booking-write operation —
 * customer checkout, admin manual bookings, admin date/vehicle changes all
 * call the same methods here (brief §6 point 8: "Admin edits use the exact
 * same service — no bypass"). Controllers never touch `vehicle_reservation_slots`
 * or a `Booking`/`BookingHold`'s `status` column directly.
 *
 * Every write method that touches availability runs inside `DB::transaction()`
 * with `AvailabilityService::lockVehicle()` taken first and `isRangeFree()`
 * re-checked *inside* that lock, immediately before
 * `AvailabilityService::reserveSlots()` — see docs/01-architecture.md §6.
 */
final class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly CustomerService $customers,
    ) {
    }

    /**
     * @param array{
     *   hold_key: string, start_at: string, end_at: string,
     *   vehicle_id?: int|null, category_id?: int|null, package_id?: int|null,
     *   customer_session_id?: string|null,
     * } $data
     */
    public function createHold(array $data): BookingHold
    {
        $existing = BookingHold::query()->where('hold_key', $data['hold_key'])->first();

        if ($existing !== null) {
            return $existing;
        }

        $start = CarbonImmutable::parse($data['start_at'])->startOfDay();
        $end = CarbonImmutable::parse($data['end_at'])->startOfDay();

        return $this->transaction(fn () => $this->createHoldWithinTransaction($data, $start, $end));
    }

    private function createHoldWithinTransaction(array $data, CarbonImmutable $start, CarbonImmutable $end): BookingHold
    {
        // Request-array input is loosely typed (HTTP form values arrive
        // as strings) — normalize once, here, rather than trusting
        // every caller to pre-cast.
        $vehicleId = isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null;

        if ($vehicleId !== null) {
            $this->availability->lockVehicle($vehicleId);

            if (! $this->availability->isRangeFree($vehicleId, $start, $end)) {
                throw NoVehicleAvailableException::make();
            }
        } else {
            $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
            $packageId = isset($data['package_id']) ? (int) $data['package_id'] : null;
            $vehicleId = $this->pickAvailableVehicle($categoryId, $packageId, $start, $end);
        }

        $hold = BookingHold::query()->create([
            'hold_key' => $data['hold_key'],
            'vehicle_id' => $vehicleId,
            'category_id' => $data['category_id'] ?? null,
            'package_id' => $data['package_id'] ?? null,
            'customer_session_id' => $data['customer_session_id'] ?? null,
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'expires_at' => now()->addMinutes((int) config('booking.hold_minutes')),
            'status' => BookingHold::STATUS_ACTIVE,
        ]);

        $this->availability->reserveSlots($vehicleId, $start, $end, $hold);

        return $hold;
    }

    /**
     * Within the caller's lock: try every candidate vehicle in turn and
     * take the first that's actually free. Candidates come from the
     * package's eligibility rule (specific vehicles > category > whole
     * active fleet — see Package::eligibleVehicleIds()) or, with no
     * package, straight from the category.
     *
     * Candidates are locked in ascending id order: two concurrent requests
     * walking the same vehicles in different orders would otherwise each
     * hold one lock while waiting for the other's — a deadlock.
     */
    private function pickAvailableVehicle(?int $categoryId, ?int $packageId, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $candidateIds = $packageId !== null
            ? Package::query()->findOrFail($packageId)->eligibleVehicleIds()
            : Vehicle::query()->active()->when($categoryId, fn ($q) => $q->inCategory($categoryId))->pluck('id')->all();

        $candidateIds = array_map('intval', $candidateIds);
        sort($candidateIds);

        foreach ($candidateIds as $candidateId) {
            $this->availability->lockVehicle($candidateId);

            if ($this->availability->isRangeFree($candidateId, $start, $end)) {
                return $candidateId;
            }
        }

        throw NoVehicleAvailableException::make();
    }

    /**
     * @param array{
     *   email: string, full_name: string, phone?: string|null, nationality?: string|null,
     *   passport_number?: string|null, locale_preference?: string|null,
     *   pickup_type?: string, business_location_id?: int|null, delivery_zone_id?: int|null,
     *   has_international_permit?: bool, special_requests?: string|null,
     * } $customerData
     * @param array<int, array{addon_id:int, quantity:int}> $addonSelections
     */
    public function confirmHold(BookingHold $hold, array $customerData, PriceBreakdown $price, array $addonSelections = []): Booking
    {
        return $this->transaction(function () use ($hold, $customerData, $price, $addonSelections) {
            /** @var BookingHold $hold */
            $hold = BookingHold::query()->lockForUpdate()->findOrFail($hold->id);

            $existingBooking = Booking::query()->where('idempotency_key', $hold->hold_key)->first();

            if ($existingBooking !== null) {
                return $existingBooking;
            }

            if ($hold->status !== BookingHold::STATUS_ACTIVE || $hold->isExpired()) {
                throw HoldExpiredException::make();
            }

            $this->availability->lockVehicle($hold->vehicle_id);

            $customer = $this->customers->findOrCreateGuest($customerData);
            $initialStatus = config('pricing.online_payment_enabled')
                ? Booking::STATUS_PENDING_PAYMENT
                : Booking::STATUS_CONFIRMED;

            $booking = Booking::query()->create([
                'reference' => BookingReference::generate(),
                'customer_id' => $customer->id,
                'vehicle_id' => $hold->vehicle_id,
                'package_id' => $hold->package_id,
                'business_location_id' => $customerData['business_location_id'] ?? null,
                'delivery_zone_id' => $customerData['delivery_zone_id'] ?? null,
                'pickup_type' => $customerData['pickup_type'] ?? 'office',
                'has_international_permit' => $customerData['has_international_permit'] ?? false,
                'special_requests' => $customerData['special_requests'] ?? null,
                'start_at' => $hold->start_at,
                'end_at' => $hold->end_at,
                'status' => $initialStatus,
                'price_breakdown' => $price->toArray(),
                'total_amount' => $price->total,
                'currency_code' => $price->currencyCode,
                'deposit_amount' => $price->depositAmount,
                ...$this->kmAllowanceSnapshot($hold->package_id, $price->days),
                'locale_at_booking' => app()->getLocale(),
                'idempotency_key' => $hold->hold_key,
            ]);

            $this->availability->repointSlots($hold, $booking);
            $hold->update(['status' => BookingHold::STATUS_CONVERTED]);

            // Usage is only counted once a hold actually converts to a
            // booking — an abandoned hold must never consume a coupon's
            // limited redemptions (see Coupon::isValidFor()'s docblock).
            //
            // Atomic "increment only while under the limit": two checkouts
            // racing for a coupon's last use both priced it as valid, but
            // only one UPDATE can match — the other rolls its booking back
            // instead of silently over-redeeming the coupon.
            if ($price->couponCode !== null) {
                $redeemed = Coupon::query()
                    ->where('code', $price->couponCode)
                    ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit'))
                    ->increment('usage_count');

                if ($redeemed === 0) {
                    throw CouponUnavailableException::make($price->couponCode);
                }
            }

            foreach ($addonSelections as $selection) {
                $addon = Addon::query()->find($selection['addon_id']);

                if ($addon !== null) {
                    $booking->addons()->create([
                        'addon_id' => $addon->id,
                        'quantity' => $selection['quantity'],
                        'unit_price' => $addon->price,
                    ]);
                }
            }

            $this->recordHistory($booking, null, $initialStatus, null, 'Booking created.');

            // Deferred until this transaction commits (ShouldDispatchAfterCommit),
            // and never fired for the idempotent early-return above.
            BookingPlaced::dispatch($booking);

            return $booking;
        });
    }

    /**
     * Brief §6 point 8 — admin manual (walk-in/phone/WhatsApp) bookings go
     * through the exact same hold-then-confirm path as the public flow,
     * just without a customer-facing review step in between.
     */
    public function createManualBooking(array $holdData, array $customerData, PriceBreakdown $price, array $addonSelections = []): Booking
    {
        $hold = $this->createHold($holdData);

        return $this->confirmHold($hold, $customerData, $price, $addonSelections);
    }

    public function cancel(Booking $booking, string $reason, ?User $admin = null): Booking
    {
        return $this->transaction(function () use ($booking, $reason, $admin) {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            BookingStateMachine::assertCanTransition($booking->status, Booking::STATUS_CANCELLED);

            $this->availability->releaseSlots($booking);

            $from = $booking->status;
            $booking->update(['status' => Booking::STATUS_CANCELLED]);

            $this->recordHistory($booking, $from, Booking::STATUS_CANCELLED, $admin, $reason);

            return $booking;
        });
    }

    /**
     * Same conflict-safe path as hold creation: release this booking's
     * current slots, lock the vehicle, re-check the new range, reserve it —
     * all inside one transaction, so a conflict on the new dates rolls the
     * release back too (the booking never ends up with no reservation at all).
     */
    public function changeDates(Booking $booking, CarbonImmutable $newStart, CarbonImmutable $newEnd, ?User $admin = null): Booking
    {
        return $this->transaction(function () use ($booking, $newStart, $newEnd, $admin) {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $this->availability->lockVehicle($booking->vehicle_id);

            $this->availability->releaseSlots($booking);

            // Slots are whole days; an hourly booking's range carries times
            // (stored on the booking as-is), so reserve by its calendar days.
            $slotStart = $newStart->startOfDay();
            $slotEnd = $newEnd->startOfDay();

            if (! $this->availability->isRangeFree($booking->vehicle_id, $slotStart, $slotEnd)) {
                throw NoVehicleAvailableException::make();
            }

            $this->availability->reserveSlots($booking->vehicle_id, $slotStart, $slotEnd, $booking);

            $booking->update(['start_at' => $newStart, 'end_at' => $newEnd]);

            $this->recordHistory($booking, $booking->status, $booking->status, $admin, 'Dates changed.');

            return $booking;
        });
    }

    /**
     * Records the odometer at pickup and return and keeps the booking's
     * single "extra km" charge in step with it: km driven beyond the
     * allowance snapshotted at booking time, at the snapshotted rate.
     * Re-entering readings recalculates (or removes) that charge.
     */
    public function recordOdometer(Booking $booking, ?int $start, ?int $end, ?User $admin = null): Booking
    {
        return $this->transaction(function () use ($booking, $start, $end, $admin) {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $booking->update(['odometer_start' => $start, 'odometer_end' => $end]);

            $extraKm = $booking->extraKm();
            $charge = $booking->extraCharges()->where('type', BookingExtraCharge::TYPE_EXTRA_KM)->first();

            if ($extraKm === 0 || ! $booking->hasKmAllowance()) {
                $charge?->delete();

                return $booking;
            }

            $booking->extraCharges()->updateOrCreate(
                ['type' => BookingExtraCharge::TYPE_EXTRA_KM],
                [
                    'amount' => round($extraKm * (float) $booking->extra_km_rate, 2),
                    'notes' => "{$extraKm} km over the {$booking->included_km} km included",
                    'created_by' => $admin?->id,
                ],
            );

            return $booking;
        });
    }

    public function reassignVehicle(Booking $booking, int $newVehicleId, ?User $admin = null): Booking
    {
        return $this->transaction(function () use ($booking, $newVehicleId, $admin) {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            $this->availability->releaseSlots($booking);
            $this->availability->lockVehicle($newVehicleId);

            $start = CarbonImmutable::parse($booking->start_at);
            $end = CarbonImmutable::parse($booking->end_at);

            if (! $this->availability->isRangeFree($newVehicleId, $start, $end)) {
                throw NoVehicleAvailableException::make();
            }

            $this->availability->reserveSlots($newVehicleId, $start, $end, $booking);

            $booking->update(['vehicle_id' => $newVehicleId]);

            $this->recordHistory($booking, $booking->status, $booking->status, $admin, "Reassigned to vehicle #{$newVehicleId}.");

            return $booking;
        });
    }

    public function markActive(Booking $booking, ?User $admin = null): Booking
    {
        return $this->transition($booking, Booking::STATUS_ACTIVE, $admin, 'Picked up.');
    }

    public function markCompleted(Booking $booking, ?User $admin = null): Booking
    {
        return $this->transition($booking, Booking::STATUS_COMPLETED, $admin, 'Returned.');
    }

    public function markNoShow(Booking $booking, ?User $admin = null): Booking
    {
        return $this->transaction(function () use ($booking, $admin) {
            $booking = $this->transition($booking, Booking::STATUS_NO_SHOW, $admin, 'Customer did not show up.');
            $this->availability->releaseSlots($booking);

            return $booking;
        });
    }

    private function transition(Booking $booking, string $to, ?User $admin, string $reason): Booking
    {
        return $this->transaction(function () use ($booking, $to, $admin, $reason) {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            BookingStateMachine::assertCanTransition($booking->status, $to);

            $from = $booking->status;
            $booking->update(['status' => $to]);

            $this->recordHistory($booking, $from, $to, $admin, $reason);

            return $booking;
        });
    }

    /**
     * DB::transaction() with two concurrency fixes for MySQL/MariaDB:
     *
     * - READ COMMITTED instead of InnoDB's default REPEATABLE READ. Under
     *   REPEATABLE READ the first plain SELECT freezes a snapshot for the
     *   whole transaction, so an isRangeFree() re-check made *after* waiting
     *   for another request's vehicle lock could still miss the slots that
     *   request just committed — the insert then hit the UNIQUE backstop and
     *   the customer got an error instead of the next free vehicle.
     *   READ COMMITTED gives every check after a lock the latest data.
     * - Up to 3 attempts, so a deadlock (rolled back by the engine) is
     *   retried transparently instead of surfacing as a 500.
     *
     * The isolation level can only be set before BEGIN, so nested calls
     * simply join the outer transaction.
     *
     * Losing a race on the UNIQUE(vehicle_id, slot_date) backstop is, to
     * the customer or admin, simply "no longer available" — the same
     * graceful NoVehicleAvailableException as losing it at isRangeFree().
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    /**
     * The package's km terms frozen onto the booking: the total allowance
     * for this booking's length, and the per-km rate beyond it.
     *
     * @return array{included_km: int|null, extra_km_rate: float|null}
     */
    private function kmAllowanceSnapshot(?int $packageId, int $days): array
    {
        $package = $packageId !== null ? Package::query()->find($packageId) : null;

        if ($package === null || ! $package->hasKmAllowance()) {
            return ['included_km' => null, 'extra_km_rate' => null];
        }

        return ['included_km' => $package->includedKmFor($days), 'extra_km_rate' => (float) $package->extra_km_rate];
    }

    private function transaction(Closure $callback): mixed
    {
        $connection = DB::connection();

        if ($connection->transactionLevel() === 0 && in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $connection->statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }

        try {
            return $connection->transaction($callback, 3);
        } catch (SlotConflictException $exception) {
            throw NoVehicleAvailableException::make($exception);
        }
    }

    private function recordHistory(Booking $booking, ?string $from, string $to, ?User $admin, string $reason): void
    {
        BookingStatusHistory::query()->create([
            'booking_id' => $booking->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $admin?->id,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
