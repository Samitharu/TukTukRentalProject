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
use Modules\Availability\Models\TransferArea;
use Modules\Availability\Services\AvailabilityService;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Http\Requests\Admin\StoreManualBookingRequest;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Booking\Services\OfferedBookingPlanner;
use Modules\Booking\Services\SuspiciousBookingDetector;
use Modules\Booking\Services\TransferAvailability;
use Modules\Booking\Support\HourlySchedule;
use Modules\Booking\Support\TransferSchedule;
use Modules\Core\Support\Countries;
use Modules\Customer\Models\Customer;
use Modules\Fleet\Models\Vehicle;
use Modules\Package\Models\Package;
use Modules\Pricing\DataObjects\PriceBreakdown;
use Modules\Pricing\Services\PricingService;
use Modules\Pricing\Support\Money;

final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly PricingService $pricing,
        private readonly SuspiciousBookingDetector $detector,
        private readonly OfferedBookingPlanner $offers,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Booking::class);

        $bookings = Booking::query()
            ->with(['customer', 'vehicle.category', 'package.productCategory'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('suspicious'), fn ($q) => $this->detector->scopeToSuspicious($q))            ->when($request->filled('q'), function ($q) use ($request) {
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
            'packageOffers' => $packages->flatMap->availableOffers(),
            'addons' => $addons->sortBy('sort_order')->values(),
            'addonPackageIds' => $addonPackageIds,
            'selectedCustomer' => $customer,
            'countries' => Countries::all(),
            'hourlyStartTimes' => HourlySchedule::startTimes(new Package(['min_hours' => 1])),
            'vehicles' => Vehicle::query()->active()->tracked()->with('category')->orderBy('plate_no')->orderBy('id')->get(),
            'transferAreas' => TransferArea::query()->active()->ordered()->get(),
        ]);
    }

    public function store(StoreManualBookingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $quote = $this->priceFor($request);
        $customer = isset($data['customer_id']) ? Customer::query()->find($data['customer_id']) : null;

        $holdData = [
            'hold_key' => (string) Str::uuid(),
            'start_at' => $quote['start_at'],
            'end_at' => $quote['end_at'],
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'package_id' => $quote['package']->id,
        ];
        // An existing customer is matched by their stored email;
        // CustomerService only fills in details they're missing.
        $customerData = [
            'email' => $customer?->email ?? $data['email'],
            'full_name' => filled($data['full_name'] ?? null) ? $data['full_name'] : $customer?->full_name,
            'phone' => $data['phone'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'passport_number' => $data['passport_number'] ?? null,
            'pickup_type' => $quote['data']['pickup_type'],
            'delivery_zone_id' => null,
            'transfer_direction' => $quote['data']['transfer_direction'] ?? null,
            'flight_number' => $quote['data']['flight_number'] ?? null,
            'transfer_address' => $quote['data']['transfer_address'] ?? null,
            'transfer_area_id' => isset($quote['data']['transfer_area_id']) ? (int) $quote['data']['transfer_area_id'] : null,
            'has_international_permit' => (bool) ($quote['data']['has_international_permit'] ?? false),
            'special_requests' => $data['special_requests'] ?? null,
        ];
        // The form always posts it (hidden "0" + checkbox); default
        // on, as before the checkbox existed.
        $notifyCustomer = $request->boolean('notify_customer', true);

        try {
            if ($quote['offer'] !== null) {
                $plan = $quote['offer']['plan'];

                $booking = $this->bookings->createBookingsTogether(
                    holdData: $holdData,
                    customerData: $customerData,
                    price: $quote['price'],
                    others: [[
                        'hold' => [
                            'hold_key' => (string) Str::uuid(),
                            'start_at' => $plan['start_at'],
                            'end_at' => $plan['end_at'],
                            'package_id' => $quote['offer']['package']->id,
                        ],
                        'customer' => [...$customerData, ...$plan['customer']],
                        'price' => $plan['price'],
                    ]],
                    notifyCustomer: $notifyCustomer,
                );
            } else {
                $booking = $this->bookings->createManualBooking(
                    holdData: $holdData,
                    customerData: $customerData,
                    price: $quote['price'],
                    notifyCustomer: $notifyCustomer,
                );
            }
        } catch (NoVehicleAvailableException $exception) {
            return back()->withInput()->withErrors(['vehicle_id' => $exception->getMessage()]);
        }

        return redirect()->route('admin.bookings.show', $booking)->with('status', __('Booking :reference created.', ['reference' => $booking->reference]));
    }

    /**
     * Live price + availability preview for the manual booking form. The
     * same validation and pricing as store(), so the total shown is the
     * total that will be booked. Availability is advisory — store()
     * re-checks under the vehicle lock.
     */
    public function quote(StoreManualBookingRequest $request, AvailabilityService $availability, TransferAvailability $transfers): JsonResponse
    {
        $quote = $this->priceFor($request);
        $price = $quote['price'];

        // Tuk tuks come from partners: no units to count.
        if ($quote['package']->hasUnlimitedUnits()) {
            return response()->json([
                ...$price->toArray(),
                'display' => $this->displayAmounts($price, $quote['offer']),
                'unlimited_units' => true,
            ]);
        }

        $vehicleIds = $request->filled('vehicle_id') ? [$request->integer('vehicle_id')] : $quote['package']->eligibleVehicleIds();
        $busy = $quote['package']->isTransfer()
            ? $transfers->unavailableVehicleIds($vehicleIds, $quote['start'], $quote['end'])
            : $availability->unavailableVehicleIds($vehicleIds, $quote['start'], $quote['end']);

        return response()->json([
            ...$price->toArray(),
            'display' => $this->displayAmounts($price, $quote['offer']),
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
     * The price preview's amounts, written out for staff.
     *
     * @param  array{package: Package, plan: array{price: PriceBreakdown}}|null  $offer
     * @return array<string, string|null>
     */
    private function displayAmounts(PriceBreakdown $price, ?array $offer = null): array
    {
        $eur = $price->eur ?? [];
        $offeredPrice = $offer['plan']['price'] ?? null;
        $bothEur = isset($eur['total'], $offeredPrice?->eur['total']) ? $eur['total'] + $offeredPrice->eur['total'] : null;

        return [
            'offer_discount' => $price->offerDiscount > 0 ? Money::dual(-$price->offerDiscount, isset($eur['offer_discount']) ? -$eur['offer_discount'] : null) : null,
            'offered_name' => $offer['package']->name ?? null,
            'offered_total' => $offeredPrice !== null ? Money::admin($offeredPrice->total, $offeredPrice->eur['total'] ?? null) : null,
            'both_total' => $offeredPrice !== null ? Money::admin($price->total + $offeredPrice->total, $bothEur) : null,
            'base' => Money::dual($price->baseAmount, $eur['base_amount'] ?? null),
            'seasonal' => Money::dual($price->seasonalAdjustment, $eur['seasonal_adjustment'] ?? null),
            'transfer' => Money::dual($price->transferFee, $eur['transfer_fee'] ?? null),
            'licence' => Money::dual($price->licenceFee, $eur['licence_fee'] ?? null),
            'addons' => Money::dual($price->addonsTotal, $eur['addons_total'] ?? null),
            'delivery' => Money::usd($price->deliveryFee),
            'total' => Money::admin($price->total, $eur['total'] ?? null),
            'deposit' => Money::usd($price->depositAmount),
        ];
    }

    /**
     * The booking period and server-side price for a manual booking form
     * submission — shared by store() and quote().
     *
     * @return array{package: Package, price: PriceBreakdown, start: CarbonImmutable, end: CarbonImmutable, start_at: string, end_at: string, data: array<string, mixed>, offer: array{package: Package, plan: array<string, mixed>}|null}
     */
    private function priceFor(StoreManualBookingRequest $request): array
    {
        $data = $request->validated();
        $package = Package::query()->findOrFail($data['package_id']);

        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);

        // A rental is always collected at the office — no delivery.
        $data = [...$data, 'pickup_type' => 'office'];
        $transferArea = null;

        if ($package->isStay()) {
            // The form's end date is the check-out day; a stay is stored up
            // to its last night (see Booking::isStay()), and has no pickup.
            $end = $end->subDay();
            $data = [...$data, 'has_international_permit' => false];
        }

        if ($package->isTransfer()) {
            // The destination area is optional.
            $transferArea = ! empty($data['transfer_area_id']) ? TransferArea::query()->findOrFail($data['transfer_area_id']) : null;
            $data = [...$data, 'has_international_permit' => false];
        } else {
            $data = [...$data, 'transfer_direction' => null, 'flight_number' => null, 'transfer_address' => null, 'transfer_area_id' => null];
        }

        // Carbon's diffInDays() returns float (fractional-day precision) —
        // cast explicitly since day counts everywhere else (pricing tiers,
        // addon per-day amounts) are int.
        $days = (int) $start->diffInDays($end) + 1;
        $hours = $package->isHourly() ? (int) $data['hours'] : null;

        // An hourly rental is one day; it keeps its real start/return times.
        // An airport pickup reserves its trip's real time slot.
        [$startAt, $endAt] = match (true) {
            $package->isTransfer() => array_map(fn (CarbonImmutable $at) => $at->toDateTimeString(), TransferSchedule::range($start->toDateString(), $data['transfer_time'], $transferArea)),
            $hours !== null => array_map(fn (CarbonImmutable $at) => $at->toDateTimeString(), HourlySchedule::range($start->toDateString(), $data['start_time'], $hours)),
            default => [$start->toDateString(), $end->toDateString()],
        };

        if ($package->isTransfer()) {
            [$start, $end] = [CarbonImmutable::parse($startAt), CarbonImmutable::parse($endAt)];
        }

        $price = $this->pricing->calculate($package, $start, $days, $request->addonSelections(), hours: $hours, transferArea: $transferArea, people: $package->isGroupPriced() ? (int) $data['people'] : null, drivers: $package->requires_licence_documents ? (int) ($data['drivers'] ?? 1) : null);
        $offer = $request->chosenOffer();

        return [
            'package' => $package,
            'price' => $offer !== null ? $this->pricing->withOffer($package, $price, $offer) : $price,
            'start' => $start,
            'end' => $end,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'data' => $data,
            'offer' => $offer !== null ? [
                'package' => $offer->offeredPackage,
                'plan' => $this->offers->plan($offer->offeredPackage, [
                    ...$request->offerData(),
                    // As ticked: the first package may be one that ignores it.
                    'has_international_permit' => $request->boolean('has_international_permit'),
                ]),
            ] : null,
        ];
    }

    public function show(Booking $booking): View
    {
        $this->authorize('view', $booking);

        $booking->load(['customer', 'vehicle.category', 'package', 'addons.addon', 'statusHistory.changedBy', 'extraCharges', 'transferArea', 'linkedBooking.package']);
        $hourlyPackage = $booking->isHourly() ? ($booking->package ?? new Package(['min_hours' => 1])) : null;

        return view('booking::admin.bookings.show', [
            'booking' => $booking,
            'flags' => $this->detector->flagsFor([$booking])[$booking->id] ?? [],
            'related' => $this->detector->relatedTo($booking),
            'canCancelThis' => $this->detector->isOpenAndUpcoming($booking),
            'ipBlock' => $booking->ip_address !== null ? BlockedIp::query()->currentlyBlocking($booking->ip_address)->first() : null,
            // Reassign only to the same kind of unit: a tuk tuk rental can't
            // move into a cabana (its dates mean days, not nights).
            'vehicles' => Vehicle::query()->active()->tracked()->ofKind($booking->kind())->orderBy('plate_no')->orderBy('id')->get(),
            'hourlyStartTimes' => $hourlyPackage !== null ? HourlySchedule::startTimes($hourlyPackage) : [],
        ]);
    }
}
