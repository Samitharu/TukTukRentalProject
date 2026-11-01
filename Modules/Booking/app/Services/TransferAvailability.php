<?php

declare(strict_types=1);

namespace Modules\Booking\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingHold;
use Modules\Fleet\Models\VehicleMaintenanceLog;

/**
 * Time-slot availability for airport pickup vehicles. Rentals and stays
 * reserve whole days through vehicle_reservation_slots (UNIQUE per vehicle
 * and day); a transfer only occupies its vehicle from the pickup time until
 * the trip is done (start + the area's duration), so several transfers can
 * share a vehicle on one day. Transfer units never get slot rows.
 *
 * The guarantee against two overlapping trips on one vehicle is the
 * vehicle row lock: every write (BookingService) takes
 * AvailabilityService::lockVehicle() inside a READ COMMITTED transaction
 * and only then calls isFree(), so the re-check sees every committed rival.
 *
 * Overlap rule, with config booking.transfer_buffer_minutes kept free
 * between trips: existing.start < new.end + buffer AND existing.end > new.start − buffer.
 */
final class TransferAvailability
{
    public function isFree(int $vehicleId, CarbonImmutable $startAt, CarbonImmutable $endAt, ?int $ignoreBookingId = null): bool
    {
        return $this->unavailableVehicleIds([$vehicleId], $startAt, $endAt, $ignoreBookingId) === [];
    }

    /**
     * Of $vehicleIds, the ones busy at any point of [$startAt, $endAt].
     * A fixed handful of queries however many vehicles are passed.
     *
     * @param  int[]  $vehicleIds
     * @return int[]
     */
    public function unavailableVehicleIds(array $vehicleIds, CarbonImmutable $startAt, CarbonImmutable $endAt, ?int $ignoreBookingId = null): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $buffer = (int) config('booking.transfer_buffer_minutes');
        $from = $startAt->subMinutes($buffer);
        $until = $endAt->addMinutes($buffer);

        $overlapping = fn (Builder $query, string $start, string $end) => $query
            ->whereIn('vehicle_id', $vehicleIds)
            ->where($start, '<', $until)
            ->where($end, '>', $from);

        $booked = $overlapping(Booking::query(), 'start_at', 'end_at')
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->when($ignoreBookingId !== null, fn ($q) => $q->whereKeyNot($ignoreBookingId))
            ->pluck('vehicle_id');

        // An abandoned checkout stops counting once its hold expires, even
        // before the release-expired-holds sweep marks it expired.
        $held = $overlapping(BookingHold::query(), 'start_at', 'end_at')
            ->where('status', BookingHold::STATUS_ACTIVE)
            ->where('expires_at', '>', now())
            ->pluck('vehicle_id');

        $inMaintenance = $overlapping(VehicleMaintenanceLog::query(), 'starts_at', 'ends_at')->pluck('vehicle_id');

        return $booked->concat($held)->concat($inMaintenance)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
