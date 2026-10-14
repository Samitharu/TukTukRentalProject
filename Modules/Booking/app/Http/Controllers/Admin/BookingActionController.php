<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Admin\Models\BlockedIp;
use Modules\Booking\Exceptions\InvalidBookingTransitionException;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Http\Requests\Admin\CancelBookingRequest;
use Modules\Booking\Http\Requests\Admin\CancelRelatedBookingsRequest;
use Modules\Booking\Http\Requests\Admin\ChangeDatesRequest;
use Modules\Booking\Http\Requests\Admin\ReassignVehicleRequest;
use Modules\Booking\Http\Requests\Admin\RecordOdometerRequest;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Booking\Services\SuspiciousBookingDetector;

final class BookingActionController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly SuspiciousBookingDetector $detector,
    ) {
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $this->bookings->cancel($booking, $request->string('reason')->toString(), $request->user());
        } catch (InvalidBookingTransitionException $exception) {
            return back()->withErrors(['reason' => $exception->getMessage()]);
        }

        return back()->with('status', __('Booking cancelled.'));
    }

    /**
     * Cancels the ticked bookings from this booking's "related bookings"
     * list in one go. Only ids from that list (or this booking) are
     * accepted, so a tampered form can't cancel unrelated bookings.
     */
    public function cancelRelated(CancelRelatedBookingsRequest $request, Booking $booking): RedirectResponse
    {
        $allowed = $this->detector->relatedTo($booking)->modelKeys();

        if ($this->detector->isOpenAndUpcoming($booking)) {
            $allowed[] = $booking->id;
        }

        $selected = array_intersect(array_map('intval', $request->input('booking_ids', [])), $allowed);
        $reason = $request->string('reason')->toString();
        $cancelled = 0;

        foreach (Booking::query()->whereKey($selected)->get() as $target) {
            try {
                $this->bookings->cancel($target, $reason, $request->user());
                $cancelled++;
            } catch (InvalidBookingTransitionException) {
                // Picked up or cancelled since the page loaded — skip it.
            }
        }

        return back()->with('status', trans_choice(':count booking cancelled.|:count bookings cancelled.', $cancelled, ['count' => $cancelled]));
    }

    /**
     * Stops this booking's IP from confirming public bookings for
     * config('booking.block_ip_days'). BlockedIp also guards the admin
     * login, so staff can't block the IP they are using themselves.
     */
    public function blockIp(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('update', $booking);

        if ($booking->ip_address === null) {
            return back()->withErrors(['ip' => __('This booking has no IP address recorded.')]);
        }

        if ($booking->ip_address === $request->ip()) {
            return back()->withErrors(['ip' => __('That is your own IP address — blocking it would lock you out of this admin panel.')]);
        }

        BlockedIp::query()->updateOrCreate(['ip' => $booking->ip_address], [
            'reason' => "Spam bookings (blocked from booking {$booking->reference})",
            'blocked_until' => now()->addDays((int) config('booking.block_ip_days', 30)),
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('status', __('IP :ip blocked from booking online for :days days.', ['ip' => $booking->ip_address, 'days' => (int) config('booking.block_ip_days', 30)]));
    }

    public function unblockIp(Booking $booking): RedirectResponse
    {
        $this->authorize('update', $booking);

        if ($booking->ip_address !== null) {
            BlockedIp::query()->where('ip', $booking->ip_address)->delete();
        }

        return back()->with('status', __('IP unblocked.'));
    }

    public function activate(Booking $booking): RedirectResponse
    {
        $this->authorize('update', $booking);

        try {
            $this->bookings->markActive($booking, request()->user());
        } catch (InvalidBookingTransitionException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return back()->with('status', __('Marked as picked up.'));
    }

    public function complete(Booking $booking): RedirectResponse
    {
        $this->authorize('update', $booking);

        try {
            $this->bookings->markCompleted($booking, request()->user());
        } catch (InvalidBookingTransitionException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return back()->with('status', __('Marked as returned.'));
    }

    public function noShow(Booking $booking): RedirectResponse
    {
        $this->authorize('update', $booking);

        try {
            $this->bookings->markNoShow($booking, request()->user());
        } catch (InvalidBookingTransitionException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return back()->with('status', __('Marked as no-show.'));
    }

    public function changeDates(ChangeDatesRequest $request, Booking $booking): RedirectResponse
    {
        [$start, $end] = $request->range();

        try {
            $this->bookings->changeDates($booking, $start, $end, $request->user());
        } catch (NoVehicleAvailableException $exception) {
            return back()->withErrors(['start_date' => $exception->getMessage()]);
        }

        return back()->with('status', __('Dates updated.'));
    }

    public function reassignVehicle(ReassignVehicleRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $this->bookings->reassignVehicle($booking, $request->integer('vehicle_id'), $request->user());
        } catch (NoVehicleAvailableException $exception) {
            return back()->withErrors(['vehicle_id' => $exception->getMessage()]);
        }

        return back()->with('status', __('Vehicle reassigned.'));
    }

    public function recordOdometer(RecordOdometerRequest $request, Booking $booking): RedirectResponse
    {
        $booking = $this->bookings->recordOdometer(
            $booking,
            $request->filled('odometer_start') ? $request->integer('odometer_start') : null,
            $request->filled('odometer_end') ? $request->integer('odometer_end') : null,
            $request->user(),
        );

        return back()->with('status', $booking->extraKm() > 0
            ? __('Odometer saved — :km extra km charged.', ['km' => $booking->extraKm()])
            : __('Odometer saved.'));
    }
}
