<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Availability\Models\BusinessLocation;
use Modules\Availability\Models\DeliveryZone;
use Modules\Booking\Exceptions\CouponUnavailableException;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Http\Requests\Admin\StoreManualBookingRequest;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Booking\Support\HourlySchedule;
use Modules\Customer\Models\Customer;
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
            ->with(['customer', 'vehicle.category'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                // Escape LIKE wildcards so "_" or "%" in the search box are
                // matched literally rather than as "any character(s)".
                $term = addcslashes(trim((string) $request->string('q')), '%_\\');

                // Matching customers are looked up once, then bookings are
                // filtered by their ids (indexed). The previous
                // orWhereHas() ran a correlated customers subquery for
                // every booking row — ~0.8 s at 120k bookings. Capped so a
                // one-letter search can't build a 60k-id IN() list.
                $customerIds = Customer::query()
                    ->where(fn ($c) => $c->where('full_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                    ->limit(1000)
                    ->pluck('id');

                // Adaptive: a specific term (a name, an email) matches few
                // bookings — filter by that id list (primary key). A broad
                // term ("a") matches thousands — there a plain OR is faster,
                // since MySQL walks created_at newest-first and stops at the
                // first 20 hits, while an id list would mean sorting them all.
                $matchIds = DB::query()
                    ->fromSub(
                        Booking::query()->select('id')->where('reference', 'like', "%{$term}%")
                            ->union(Booking::query()->select('id')->whereIn('customer_id', $customerIds)),
                        'search_matches',
                    )
                    ->limit(2001)
                    ->pluck('id');

                $matchIds->count() <= 2000
                    ? $q->whereIn('bookings.id', $matchIds)
                    : $q->where(fn ($q2) => $q2->where('reference', 'like', "%{$term}%")->orWhereIn('customer_id', $customerIds));
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
            'packages' => Package::query()->active()->bookable()->orderBy('kind')->orderBy('sort_order')->get(),
            'hourlyStartTimes' => HourlySchedule::startTimes(new Package(['min_hours' => 1])),
            'vehicles' => Vehicle::query()->active()->with('category')->orderBy('plate_no')->orderBy('id')->get(),
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

        if ($package->isStay()) {
            // The form's end date is the check-out day; a stay is stored up
            // to its last night (see Booking::isStay()), and has no pickup.
            $end = $end->subDay();
            $data = [...$data, 'pickup_type' => 'office', 'business_location_id' => null, 'delivery_zone_id' => null, 'has_international_permit' => false];
        }

        // Carbon's diffInDays() returns float (fractional-day precision) —
        // cast explicitly since day counts everywhere else (pricing tiers,
        // addon per-day amounts) are int.
        $days = (int) $start->diffInDays($end) + 1;
        $hours = $package->isHourly() ? (int) $data['hours'] : null;

        // An hourly rental is one day; it keeps its real start/return times.
        [$startAt, $endAt] = $hours !== null
            ? array_map(fn (CarbonImmutable $at) => $at->toDateTimeString(), HourlySchedule::range($start->toDateString(), $data['start_time'], $hours))
            : [$start->toDateString(), $end->toDateString()];

        $addonIds = $data['addon_ids'] ?? [];
        $addonSelections = array_fill_keys($addonIds, 1);

        $coupon = ! empty($data['coupon_code'])
            ? Coupon::query()->where('code', mb_strtoupper($data['coupon_code']))->active()->first()
            : null;

        $deliveryZone = $data['pickup_type'] === 'delivery' && ! empty($data['delivery_zone_id'])
            ? DeliveryZone::query()->find($data['delivery_zone_id'])
            : null;

        $price = $this->pricing->calculate($package, $start, $days, $addonSelections, $coupon, $deliveryZone, hours: $hours);

        try {
            $booking = $this->bookings->createManualBooking(
                holdData: [
                    'hold_key' => (string) Str::uuid(),
                    'start_at' => $startAt,
                    'end_at' => $endAt,
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
        } catch (CouponUnavailableException) {
            return back()->withInput()->withErrors(['coupon_code' => __('That coupon has reached its usage limit.')]);
        }

        return redirect()->route('admin.bookings.show', $booking)->with('status', __('Booking :reference created.', ['reference' => $booking->reference]));
    }

    public function show(Booking $booking): View
    {
        $this->authorize('view', $booking);

        $booking->load(['customer', 'vehicle.category', 'package', 'addons.addon', 'statusHistory.changedBy', 'extraCharges']);
        $hourlyPackage = $booking->isHourly() ? ($booking->package ?? new Package(['min_hours' => 1])) : null;

        return view('booking::admin.bookings.show', [
            'booking' => $booking,
            // Reassign only to the same kind of unit: a tuk tuk rental can't
            // move into a cabana (its dates mean days, not nights).
            'vehicles' => Vehicle::query()->active()->ofKind($booking->vehicle->kind())->orderBy('plate_no')->orderBy('id')->get(),
            'hourlyStartTimes' => $hourlyPackage !== null ? HourlySchedule::startTimes($hourlyPackage) : [],
        ]);
    }
}
