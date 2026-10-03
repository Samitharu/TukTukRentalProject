<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Availability\Models\BusinessLocation;
use Modules\Availability\Models\DeliveryZone;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Http\Requests\Admin\StoreManualBookingRequest;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Fleet\Models\Vehicle;
use Modules\Package\Models\Package;
use Modules\Pricing\Models\Coupon;
use Modules\Pricing\Services\PricingService;

final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly PricingService $pricing,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Booking::class);

        $bookings = Booking::query()
            ->with(['customer', 'vehicle'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q');
                $q->where(fn ($q2) => $q2
                    ->where('reference', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($q3) => $q3
                        ->where('full_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")));
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('booking::admin.bookings.index', [
            'bookings' => $bookings,
            'statuses' => [
                Booking::STATUS_HOLD, Booking::STATUS_PENDING_PAYMENT, Booking::STATUS_CONFIRMED,
                Booking::STATUS_ACTIVE, Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED,
                Booking::STATUS_NO_SHOW, Booking::STATUS_EXPIRED,
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Booking::class);

        return view('booking::admin.bookings.create', [
            'packages' => Package::query()->active()->orderBy('sort_order')->get(),
            'vehicles' => Vehicle::query()->active()->orderBy('plate_no')->get(),
            'locations' => BusinessLocation::query()->active()->get(),
            'deliveryZones' => DeliveryZone::query()->active()->get(),
        ]);
    }

    public function store(StoreManualBookingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $package = Package::query()->findOrFail($data['package_id']);

        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        // Carbon's diffInDays() returns float (fractional-day precision) —
        // cast explicitly since day counts everywhere else (pricing tiers,
        // addon per-day amounts) are int.
        $days = (int) $start->diffInDays($end) + 1;

        $addonIds = $data['addon_ids'] ?? [];
        $addonSelections = array_fill_keys($addonIds, 1);

        $coupon = ! empty($data['coupon_code'])
            ? Coupon::query()->where('code', mb_strtoupper($data['coupon_code']))->active()->first()
            : null;

        $deliveryZone = $data['pickup_type'] === 'delivery' && ! empty($data['delivery_zone_id'])
            ? DeliveryZone::query()->find($data['delivery_zone_id'])
            : null;

        $price = $this->pricing->calculate($package, $start, $days, $addonSelections, $coupon, $deliveryZone);

        try {
            $booking = $this->bookings->createManualBooking(
                holdData: [
                    'hold_key' => (string) Str::uuid(),
                    'start_at' => $data['start_date'],
                    'end_at' => $data['end_date'],
                    'vehicle_id' => $data['vehicle_id'] ?? null,
                    'package_id' => $package->id,
                ],
                customerData: [
                    'email' => $data['email'],
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'] ?? null,
                    'nationality' => $data['nationality'] ?? null,
                    'pickup_type' => $data['pickup_type'],
                    'business_location_id' => $data['business_location_id'] ?? null,
                    'delivery_zone_id' => $deliveryZone?->id,
                    'has_international_permit' => (bool) ($data['has_international_permit'] ?? false),
                    'special_requests' => $data['special_requests'] ?? null,
                ],
                price: $price,
                addonSelections: array_map(fn (int $id) => ['addon_id' => $id, 'quantity' => 1], $addonIds),
            );
        } catch (NoVehicleAvailableException $exception) {
            return back()->withInput()->withErrors(['vehicle_id' => $exception->getMessage()]);
        }

        return redirect()->route('admin.bookings.show', $booking)->with('status', __('Booking :reference created.', ['reference' => $booking->reference]));
    }

    public function show(Booking $booking): View
    {
        $this->authorize('view', $booking);

        $booking->load(['customer', 'vehicle', 'package', 'addons.addon', 'statusHistory.changedBy', 'extraCharges']);

        return view('booking::admin.bookings.show', [
            'booking' => $booking,
            'vehicles' => Vehicle::query()->active()->orderBy('plate_no')->get(),
        ]);
    }
}
