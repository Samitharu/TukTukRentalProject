<?php

declare(strict_types=1);

namespace Modules\Availability\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Modules\Availability\Exceptions\SlotConflictException;
use Modules\Availability\Models\AvailabilityBlackout;
use Modules\Availability\Models\VehicleReservationSlot;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleMaintenanceLog;

/**
 * The conflict-prevention system's single point of contact with
 * `vehicle_reservation_slots` (docs/01-architecture.md §6) — both the read
 * side (`isRangeFree`, queried by the public availability calendar and by
 * every write below before it writes) and the write side (`reserveSlots`
 * / `releaseSlots` / `repointSlots`), used identically by holds and
 * confirmed bookings so neither can bypass the same checks (brief §6 point 8).
 *
 * `reserveSlots()` must always be called from inside a
 * `DB::transaction()` that has already taken `lockForUpdate()` on the
 * vehicle row (see `lockVehicle()`) — the lock reduces contention between
 * concurrent attempts for the *same* vehicle, but the actual, final
 * guarantee against a double-booking slipping through is the
 * `UNIQUE(vehicle_id, slot_date)` constraint itself, which this method
 * surfaces as a `SlotConflictException` rather than a raw `QueryException`.
 */
final class AvailabilityService
{
    /**
     * Overlap rule throughout: existing.start <= new.end AND existing.end >= new.start.
     * $start/$end are calendar dates (day granularity, matching vehicle_reservation_slots).
     */
    public function isRangeFree(int $vehicleId, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return ! $this->hasBlackoutConflict($vehicleId, $start, $end)
            && ! $this->hasMaintenanceConflict($vehicleId, $start, $end)
            && ! $this->hasReservationConflict($vehicleId, $start, $end);
    }

    /**
     * Active vehicles in a category that are free for the whole range —
     * the pool Booking's automatic-vehicle-assignment (Phase 4) picks from
     * when a customer books a package/category rather than a specific vehicle.
     *
     * @return Collection<int, Vehicle>
     */
    public function availableVehiclesInCategory(int $categoryId, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Vehicle::query()
            ->active()
            ->inCategory($categoryId)
            ->get()
            ->filter(fn (Vehicle $vehicle) => $this->isRangeFree($vehicle->id, $start, $end))
            ->values();
    }

    /**
     * Of $vehicleIds, the ones that are NOT free for the whole range — the
     * same three rules as isRangeFree(), but 3-4 queries for the whole list
     * instead of 3 per vehicle. Advisory only (e.g. the public package step
     * flagging fully-booked packages): every write still re-checks with
     * isRangeFree() under the vehicle lock.
     *
     * @param  int[]  $vehicleIds
     * @return int[]
     */
    public function unavailableVehicleIds(array $vehicleIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $fleetWideBlackout = AvailabilityBlackout::query()
            ->whereNull('vehicle_id')
            ->where('starts_on', '<=', $end->toDateString())
            ->where('ends_on', '>=', $start->toDateString())
            ->exists();

        if ($fleetWideBlackout) {
            return array_values(array_map('intval', $vehicleIds));
        }

        $bufferDays = (int) config('availability.buffer_days');

        $reserved = VehicleReservationSlot::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereBetween('slot_date', [
                $start->subDays($bufferDays)->toDateString(),
                $end->addDays($bufferDays)->toDateString(),
            ])
            ->distinct()
            ->pluck('vehicle_id');

        $blackedOut = AvailabilityBlackout::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('starts_on', '<=', $end->toDateString())
            ->where('ends_on', '>=', $start->toDateString())
            ->pluck('vehicle_id');

        $inMaintenance = VehicleMaintenanceLog::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('starts_at', '<=', $end->endOfDay())
            ->where('ends_at', '>=', $start->startOfDay())
            ->pluck('vehicle_id');

        return $reserved->concat($blackedOut)->concat($inMaintenance)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function hasBlackoutConflict(int $vehicleId, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return AvailabilityBlackout::query()->overlapping($vehicleId, $start, $end)->exists();
    }

    /**
     * Maintenance logs are DATETIME ranges while rentals are whole calendar
     * days (the return day is reserved in full), so the rental side is
     * widened to [start 00:00, end 23:59:59] — otherwise maintenance that
     * starts at, say, 09:00 on the return day was not seen as a conflict.
     */
    public function hasMaintenanceConflict(int $vehicleId, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return VehicleMaintenanceLog::query()
            ->where('vehicle_id', $vehicleId)
            ->where('starts_at', '<=', $end->endOfDay())
            ->where('ends_at', '>=', $start->startOfDay())
            ->exists();
    }

    /**
     * Existing reservation slots for this vehicle within the (buffer-expanded)
     * requested range. Buffer is applied symmetrically to the request rather
     * than to each stored slot — mathematically equivalent, one query.
     */
    public function hasReservationConflict(int $vehicleId, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        $bufferDays = (int) config('availability.buffer_days');

        return VehicleReservationSlot::query()
            ->where('vehicle_id', $vehicleId)
            ->whereBetween('slot_date', [
                $start->subDays($bufferDays)->toDateString(),
                $end->addDays($bufferDays)->toDateString(),
            ])
            ->exists();
    }

    public function meetsMinimumNotice(CarbonImmutable $pickupAt, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return $now->diffInHours($pickupAt, false) >= config('availability.minimum_notice_hours');
    }

    public function withinAdvanceWindow(CarbonImmutable $pickupAt, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return $now->diffInDays($pickupAt, false) <= config('availability.maximum_advance_days');
    }

    /**
     * Acquires the row lock every reservation write must hold for its
     * whole transaction. Call this first, inside `DB::transaction()`,
     * before re-checking `isRangeFree()` and calling `reserveSlots()`.
     */
    public function lockVehicle(int $vehicleId): Vehicle
    {
        return Vehicle::query()->lockForUpdate()->findOrFail($vehicleId);
    }

    /**
     * Writes one slot row per calendar day in [$start, $end] (inclusive) for
     * $holdable (a BookingHold or a Booking). A duplicate-key error on the
     * underlying UNIQUE constraint — another transaction won the race for
     * one of these exact days — is caught and converted; nothing is left
     * half-written (Laravel wraps the whole `insert()` call, and the
     * caller's own transaction wraps this call, so any exception rolls the
     * complete attempt back).
     */
    public function reserveSlots(int $vehicleId, CarbonImmutable $start, CarbonImmutable $end, Model $holdable): void
    {
        $rows = collect(CarbonPeriod::create($start, $end))
            ->map(fn (\DateTimeInterface $date) => [
                'vehicle_id' => $vehicleId,
                'slot_date' => $date->format('Y-m-d'),
                'holdable_type' => $holdable->getMorphClass(),
                'holdable_id' => $holdable->getKey(),
                'created_at' => now(),
            ])
            ->all();

        try {
            VehicleReservationSlot::query()->insert($rows);
        } catch (QueryException $exception) {
            if ((int) $exception->getCode() === 23000) {
                throw SlotConflictException::forVehicle($vehicleId);
            }

            throw $exception;
        }
    }

    public function releaseSlots(Model $holdable): void
    {
        $this->releaseSlotsForHoldables($holdable->getMorphClass(), [$holdable->getKey()]);
    }

    /**
     * releaseSlots() for many holdables of one type in a single DELETE
     * (served by the holdable_type/holdable_id index) — for bulk sweeps
     * such as releasing a backlog of expired holds.
     *
     * @param  array<int, int|string>  $holdableIds
     */
    public function releaseSlotsForHoldables(string $morphClass, array $holdableIds): void
    {
        if ($holdableIds === []) {
            return;
        }

        VehicleReservationSlot::query()
            ->where('holdable_type', $morphClass)
            ->whereIn('holdable_id', $holdableIds)
            ->delete();
    }

    /**
     * Hold → Booking conversion: re-points the *same* slot rows to the new
     * holdable instead of deleting and re-inserting them, so there is never
     * a window where the vehicle briefly has no reservation for these dates.
     */
    public function repointSlots(Model $from, Model $to): void
    {
        VehicleReservationSlot::query()
            ->where('holdable_type', $from->getMorphClass())
            ->where('holdable_id', $from->getKey())
            ->update([
                'holdable_type' => $to->getMorphClass(),
                'holdable_id' => $to->getKey(),
            ]);
    }
}
