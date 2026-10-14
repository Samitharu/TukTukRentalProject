<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Admin\Models\BlockedIp;
use Modules\Availability\Models\BusinessLocation;
use Modules\Availability\Models\DeliveryZone;
use Modules\Availability\Services\AvailabilityService;
use Modules\Booking\Exceptions\CouponUnavailableException;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Http\Requests\Admin\StoreManualBookingRequest;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Booking\Services\SuspiciousBookingDetector;
use Modules\Booking\Support\HourlySchedule;
use Modules\Core\Support\Countries;
use Modules\Customer\Models\Customer;
use Modules\Fleet\Models\Vehicle;
use Modules\Package\Models\Package;
use Modules\Pricing\DataObjects\PriceBreakdown;
use Modules\Pricing\Models\Coupon;
use Modules\Pricing\Services\PricingService;

final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly PricingService $pricing,
        private readonly SuspiciousBookingDetector $detector,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Booking::class);

        $bookings = Booking::query()
            ->with(['customer', 'vehicle.category'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('suspicious'), fn ($q) => $this->detector->scopeToSuspicious($q))
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
            'flags' => $this->detector->flagsFor($bookings->getCollection()),
            'statuses' => [
                Booking::STATUS_HOLD, Booking::STATUS_PENDING_PAYMENT, Booking::STATUS_CONFIRMED,
                Booking::STATUS_ACTIVE, Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED,
                Booking::STATUS_NO_SHOW, Booking::STATUS_EXPIRED,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Booking::class);

        // "New booking for this customer" on the customer page — or the
        // customer already picked, when the form comes back with errors.
        $customerId = $request->integer('customer') ?: (int) old('customer_id');
        $customer = $customerId > 0 ? Customer::query()->find($customerId) : null;

        $packages = Package::query()->active()->bookable()
            ->with(['addons' => fn ($q) => $q->where('addons.is_active', true)->orderBy('addons.sort_order')])
            ->orderBy('kind')->orderBy('sort_order')->get();

        // Add-ons a customer can choose (not the ones a package already
        // includes), each tagged with the packages offering it so the form
        // shows only those for the selected package.
        $addonPackageIds = [];
        $addons = collect();

        foreach ($packages as $package) {
            foreach ($package->addons as $addon) {
                if (! $addon->pivot->is_included) {
                    $addonPackageIds[$addon->id][] = $package->id;
                    $addons->put($addon->id, $addon);
                }
            }
        }

        return view('booking::admin.bookings.create', [
            'packages' => $packages,
            'addons' => $addons->sortBy('sort_order')->values(),
            'addonPackageIds' => $addonPackageIds,
            'selectedCustomer' => $customer,
            'countries' => Countries::all(),
            'hourlyStartTimes' => HourlySchedule::startTimes(new Package(['min_hours' => 1])),
            'vehicles' => Vehicle::query()->active()->with('category')->orderBy('plate_no')->orderBy('id')->get(),
            'locations' => BusinessLocation::query()->active()->get(),
            'deliveryZones' => DeliveryZone::query()->active()->get(),
        ]);
    }

    public function store(StoreManualBookingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $quote = $this->priceFor($request);
        $customer = isset($data['customer_id']) ? Customer::query()->find($data['customer_id']) : null;

        try {
            $booking = $this->bookings->createManualBooking(
                holdData: [
                    'hold_key' => (string) Str::uuid(),
                    'start_at' => $quote['start_at'],
                    'end_at' => $quote['end_at'],
                    'vehicle_id' => $data['vehicle_id'] ?? null,
                    'package_id' => $quote['package']->id,
                ],
                // An existing customer is matched by their stored email;
                // CustomerService only fills in details they're missing.
                customerData: [
                    'email' => $customer?->email ?? $data['email'],
                    'full_name' => filled($data['full_name'] ?? null) ? $data['full_name'] : $customer?->full_name,
                    'phone' => $data['phone'] ?? null,
                    'nationality' => $data['nationality'] ?? null,
                    'passport_number' => $data['passport_number'] ?? null,
                    'pickup_type' => $quote['data']['pickup_type'],
                    'business_location_id' => $quote['data']['business_location_id'] ?? null,
                    'delivery_zone_id' => $quote['delivery_zone']?->id,
                    'has_international_permit' => (bool) ($quote['data']['has_international_permit'] ?? false),
                    'special_requests' => $data['special_requests'] ?? null,
                ],
                price: $quote['price'],
                // The form always posts it (hidden "0" + checkbox); default
                // on, as before the checkbox existed.
                notifyCustomer: $request->boolean('notify_customer', true),
            );
        } catch (NoVehicleAvailableException $exception) {
            return back()->withInput()->withErrors(['vehicle_id' => $exception->getMessage()]);
        } catch (CouponUnavailableException) {
            return back()->withInput()->withErrors(['coupon_code' => __('That coupon has reached its usage limit.')]);
        }

        return redirect()->route('admin.bookings.show', $booking)->with('status', __('Booking :reference created.', ['reference' => $booking->reference]));
    }

    /**
     * Live price + availability preview for the manual booking form. The
     * same validation and pricing as store(), so the total shown is the
     * total that will be booked. Availability is advisory — store()
     * re-checks under the vehicle lock.
     */
    public function quote(StoreManualBookingRequest $request, AvailabilityService $availability): JsonResponse
    {
        $quote = $this->priceFor($request);
        $price = $quote['price'];

        $vehicleIds = $request->filled('vehicle_id') ? [$request->integer('vehicle_id')] : $quote['package']->eligibleVehicleIds();
        $busy = $availability->unavailableVehicleIds($vehicleIds, $quote['start'], $quote['end']);

        return response()->json([
            ...$price->toArray(),
            'coupon_applied' => $price->couponCode !== null,
            'units_total' => count($vehicleIds),
            'units_free' => count(array_diff($vehicleIds, $busy)),
        ]);
    }

    /** Existing customers matching a name, email or phone, for the form's picker. */
    public function customerLookup(Request $request): JsonResponse
    {
        $this->authorize('create', Booking::class);
        $this->authorize('viewAny', Customer::class);

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $customers = Customer::query()
            ->where(fn ($q) => $q->where('full_name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'full_name', 'email', 'phone', 'nationality']);

        return response()->json($customers->map(fn (Customer $customer) => [
            'id' => $customer->id,
            'full_name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'nationality' => $customer->nationality,
        ]));
    }

    /**
     * The booking period and server-side price for a manual booking form
     * submission — shared by store() and quote().
     *
     * @return array{package: Package, price: PriceBreakdown, start: CarbonImmutable, end: CarbonImmutable, start_at: string, end_at: string, delivery_zone: DeliveryZone|null, data: array<string, mixed>}
     */
    private function priceFor(StoreManualBookingRequest $request): array
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

        $coupon = ! empty($data['coupon_code'])
            ? Coupon::query()->where('code', mb_strtoupper($data['coupon_code']))->active()->first()
            : null;

        $deliveryZone = $data['pickup_type'] === 'delivery' && ! empty($data['delivery_zone_id'])
            ? DeliveryZone::query()->find($data['delivery_zone_id'])
            : null;

        return [
            'package' => $package,
            'price' => $this->pricing->calculate($package, $start, $days, $request->addonSelections(), $coupon, $deliveryZone, hours: $hours),
            'start' => $start,
            'end' => $end,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'delivery_zone' => $deliveryZone,
            'data' => $data,
        ];
    }

    public function show(Booking $booking): View
    {
        $this->authorize('view', $booking);

        $booking->load(['customer', 'vehicle.category', 'package', 'addons.addon', 'statusHistory.changedBy', 'extraCharges']);
        $hourlyPackage = $booking->isHourly() ? ($booking->package ?? new Package(['min_hours' => 1])) : null;

        return view('booking::admin.bookings.show', [
            'booking' => $booking,
            'flags' => $this->detector->flagsFor([$booking])[$booking->id] ?? [],
            'related' => $this->detector->relatedTo($booking),
            'canCancelThis' => $this->detector->isOpenAndUpcoming($booking),
            'ipBlock' => $booking->ip_address !== null ? BlockedIp::query()->currentlyBlocking($booking->ip_address)->first() : null,
            // Reassign only to the same kind of unit: a tuk tuk rental can't
            // move into a cabana (its dates mean days, not nights).
            'vehicles' => Vehicle::query()->active()->ofKind($booking->vehicle->kind())->orderBy('plate_no')->orderBy('id')->get(),
            'hourlyStartTimes' => $hourlyPackage !== null ? HourlySchedule::startTimes($hourlyPackage) : [],
        ]);
    }
}
