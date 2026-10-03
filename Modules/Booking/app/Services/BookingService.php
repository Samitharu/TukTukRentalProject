<?php

declare(strict_types=1);

namespace Modules\Booking\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Availability\Services\AvailabilityService;
use Modules\Booking\Exceptions\HoldExpiredException;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Models\Booking;
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

        return DB::transaction(function () use ($data, $start, $end) {
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
        });
    }

    /**
     * Within the caller's lock: try every candidate vehicle in turn and
     * take the first that's actually free. Candidates come from the
     * package's eligibility rule (specific vehicles > category > whole
     * active fleet — see Package::eligibleVehicleIds()) or, with no
     * package, straight from the category.
     */
    private function pickAvailableVehicle(?int $categoryId, ?int $packageId, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $candidateIds = $packageId !== null
            ? Package::query()->findOrFail($packageId)->eligibleVehicleIds()
            : Vehicle::query()->active()->when($categoryId, fn ($q) => $q->inCategory($categoryId))->pluck('id')->all();

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
        return DB::transaction(function () use ($hold, $customerData, $price, $addonSelections) {
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
                'locale_at_booking' => app()->getLocale(),
                'idempotency_key' => $hold->hold_key,
            ]);

            $this->availability->repointSlots($hold, $booking);
            $hold->update(['status' => BookingHold::STATUS_CONVERTED]);

            // Usage is only counted once a hold actually converts to a
            // booking — an abandoned hold must never consume a coupon's
            // limited redemptions (see Coupon::isValidFor()'s docblock).
            if ($price->couponCode !== null) {
                Coupon::query()->where('code', $price->couponCode)->increment('usage_count');
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
        return DB::transaction(function () use ($booking, $reason, $admin) {
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
        return DB::transaction(function () use ($booking, $newStart, $newEnd, $admin) {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $this->availability->lockVehicle($booking->vehicle_id);

            $this->availability->releaseSlots($booking);

            if (! $this->availability->isRangeFree($booking->vehicle_id, $newStart, $newEnd)) {
                throw NoVehicleAvailableException::make();
            }

            $this->availability->reserveSlots($booking->vehicle_id, $newStart, $newEnd, $booking);

            $booking->update(['start_at' => $newStart, 'end_at' => $newEnd]);

            $this->recordHistory($booking, $booking->status, $booking->status, $admin, 'Dates changed.');

            return $booking;
        });
    }

    public function reassignVehicle(Booking $booking, int $newVehicleId, ?User $admin = null): Booking
    {
        return DB::transaction(function () use ($booking, $newVehicleId, $admin) {
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
        return DB::transaction(function () use ($booking, $admin) {
            $booking = $this->transition($booking, Booking::STATUS_NO_SHOW, $admin, 'Customer did not show up.');
            $this->availability->releaseSlots($booking);

            return $booking;
        });
    }

    private function transition(Booking $booking, string $to, ?User $admin, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $to, $admin, $reason) {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            BookingStateMachine::assertCanTransition($booking->status, $to);

            $from = $booking->status;
            $booking->update(['status' => $to]);

            $this->recordHistory($booking, $from, $to, $admin, $reason);

            return $booking;
        });
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
