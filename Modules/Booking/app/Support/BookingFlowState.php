<?php

declare(strict_types=1);

namespace Modules\Booking\Support;

use Illuminate\Support\Facades\Session;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Package\Models\Package;

/**
 * The public multi-step booking wizard's state lives in the session
 * between steps (not a client-side SPA) — simple, robust, works without
 * heavy JS, and accessible by default. Only the review step's price
 * recalculation is a JSON/no-reload call (brief §4), since that's the one
 * place the brief explicitly asks for it.
 */
final class BookingFlowState
{
    private const string SESSION_KEY = 'booking_flow';

    /**
     * What the wizard books online: the unit kinds, plus tour plans (booked
     * by the day like a rental, but reserving no unit). Activities are
     * booked by message instead.
     *
     * @var string[]
     */
    public const array KINDS = [...VehicleCategory::KINDS, Package::KIND_TOUR];

    public static function get(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    public static function put(array $data): void
    {
        Session::put(self::SESSION_KEY, [...self::get(), ...$data]);
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        return self::get()[$key] ?? $default;
    }

    public static function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public static function hasDates(): bool
    {
        return self::value('start_date') !== null && self::value('end_date') !== null;
    }

    public static function hasPackageOrVehicle(): bool
    {
        return self::value('package_id') !== null || self::value('vehicle_id') !== null;
    }

    /**
     * What is being booked: a rental ('vehicle', the default), a stay
     * ('stay'), an airport pickup ('transfer') or a tour plan ('tour'). Set
     * at the start of the flow from the line, package or unit the customer
     * came from (see BookingFlowController::start()).
     */
    public static function kind(): string
    {
        $kind = self::value('kind');

        return in_array($kind, self::KINDS, true) ? $kind : VehicleCategory::KIND_VEHICLE;
    }

    /**
     * A tour plan: the same day-inclusive range as a rental (first day to
     * last day, charged per day), but nothing is collected from the office.
     */
    public static function isTour(): bool
    {
        return self::kind() === Package::KIND_TOUR;
    }

    /**
     * The product line (Tuk Tuk, Vehicle Rent, Airport Pickup, Stays) being
     * booked, or null for "any line of this kind" — old sessions, or a
     * customer who came straight to /booking/start.
     */
    public static function lineId(): ?int
    {
        $lineId = self::value('line_id');

        return $lineId !== null ? (int) $lineId : null;
    }

    /**
     * For an airport pickup, start_date = end_date = the pickup day, and
     * the real times come from transfer_time + the area's duration (see
     * TransferSchedule::range()). It counts as a one-day booking.
     */
    public static function isTransfer(): bool
    {
        return self::kind() === VehicleCategory::KIND_TRANSFER;
    }

    /**
     * For a stay, `end_date` in this state is the LAST NIGHT (check-out
     * minus one day) — the same day-inclusive range a rental uses, so
     * pricing, availability and the reservation slots need no special
     * case. Only the dates form and the displays convert to check-out.
     */
    public static function isStay(): bool
    {
        return self::kind() === VehicleCategory::KIND_STAY;
    }
}
