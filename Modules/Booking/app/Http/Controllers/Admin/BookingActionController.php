<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Booking\Exceptions\InvalidBookingTransitionException;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Http\Requests\Admin\CancelBookingRequest;
use Modules\Booking\Http\Requests\Admin\ChangeDatesRequest;
use Modules\Booking\Http\Requests\Admin\ReassignVehicleRequest;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;

final class BookingActionController extends Controller
{
    public function __construct(private readonly BookingService $bookings)
    {
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
}
