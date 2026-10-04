<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Availability\Models\BusinessLocation;
use Modules\Availability\Models\DeliveryZone;
use Modules\Availability\Services\AvailabilityService;
use Modules\Booking\Exceptions\CouponUnavailableException;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Http\Requests\Front\StepAddonsRequest;
use Modules\Booking\Http\Requests\Front\StepDatesRequest;
use Modules\Booking\Http\Requests\Front\StepDriverDetailsRequest;
use Modules\Booking\Http\Requests\Front\StepPackageRequest;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingHold;
use Modules\Booking\Services\BookingService;
use Modules\Booking\Support\BookingFlowState;
use Modules\Booking\Support\BookingReference;
use Modules\CMS\Models\Review;
use Modules\Core\Support\Countries;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Package\Models\Package;
use Modules\Pricing\DataObjects\PriceBreakdown;
use Modules\Pricing\Models\Coupon;
use Modules\Pricing\Services\PricingService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public 5-step booking wizard (brief §4). State lives server-side in
 * the session via BookingFlowState between steps — see that class's
 * docblock for why. A step's GET handler always re-checks that the prior
 * steps are actually complete (never trusts the URL alone), redirecting
 * back to the earliest missing step rather than erroring.
 *
 * No BookingHold is created until the very last moment (confirm()): since
 * online payment is disabled for now, there is nothing for a lingering
 * hold to protect between "review" and "pay" — createHold() and
 * confirmHold() run back-to-back in one request, exactly like
 * BookingService::createManualBooking() already does for admin walk-ins.
 */
final class BookingFlowController extends Controller
{
    /** @var string[] */
    private const array REVIEWABLE_STATUSES = [
        Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE, Booking::STATUS_COMPLETED,
    ];

    public function __construct(
        private readonly BookingService $bookings,
        private readonly PricingService $pricing,
        private readonly AvailabilityService $availability,
    ) {
    }

    public function start(Request $request): View
    {
        $this->switchKindFor($request);

        if ($request->filled('package')) {
            BookingFlowState::put(['package_id' => $request->integer('package')]);
        }

        if ($request->filled('vehicle')) {
            BookingFlowState::put(['vehicle_id' => $request->integer('vehicle')]);
        }

        return view('booking::front.steps.dates', [
            'state' => BookingFlowState::get(),
            'isStay' => BookingFlowState::isStay(),
            'locations' => BusinessLocation::query()->active()->get(),
            'deliveryZones' => DeliveryZone::query()->active()->get(),
        ]);
    }

    public function storeDates(StepDatesRequest $request): RedirectResponse
    {
        BookingFlowState::put([...$request->flowData(), 'hold_key' => null]);

        return redirect()->route('booking.package');
    }

    public function package(): View|RedirectResponse
    {
        if (! BookingFlowState::hasDates()) {
            return redirect()->route('booking.start');
        }

        $days = $this->daysFromState();

        $packages = Package::query()
            ->active()
            ->currentlyValid()
            ->ofKind(BookingFlowState::kind())
            ->where('min_days', '<=', $days)
            ->where(fn ($q) => $q->whereNull('max_days')->orWhere('max_days', '>=', $days))
            ->with(['pricingTiers', 'images', 'vehicles:id,status', 'categories:id'])
            ->orderBy('sort_order')
            ->get();

        $vehicleId = BookingFlowState::value('vehicle_id');
        [$start, $end] = $this->datesFromState();
        $unavailable = [];
        $freeCounts = $this->freeVehicleCounts($packages, $start, $end, $unavailable);

        return view('booking::front.steps.package', [
            'state' => BookingFlowState::get(),
            'isStay' => BookingFlowState::isStay(),
            'packages' => $packages,
            'days' => $days,
            'freeCounts' => $freeCounts,
            'preselectedVehicle' => $vehicleId !== null ? Vehicle::query()->find($vehicleId) : null,
            'preselectedVehicleBooked' => $vehicleId !== null && in_array((int) $vehicleId, $unavailable, true),
        ]);
    }

    public function storePackage(StepPackageRequest $request): RedirectResponse
    {
        if (! BookingFlowState::hasDates()) {
            return redirect()->route('booking.start');
        }

        $package = Package::query()->active()->currentlyValid()->ofKind(BookingFlowState::kind())->findOrFail($request->validated('package_id'));
        $days = $this->daysFromState();

        if ($days < $package->min_days || ($package->max_days !== null && $days > $package->max_days)) {
            return back()->withErrors(['package_id' => $this->notEligibleMessage()]);
        }

        [$start, $end] = $this->datesFromState();
        $eligibleIds = $package->eligibleVehicleIds();
        $unavailable = $this->availability->unavailableVehicleIds($eligibleIds, $start, $end);

        if (array_diff($eligibleIds, $unavailable) === []) {
            return back()->withErrors(['package_id' => __('core::front.booking_package_fully_booked_error')]);
        }

        $vehicleId = BookingFlowState::value('vehicle_id');

        if ($vehicleId !== null
            && (! in_array((int) $vehicleId, $eligibleIds, true) || in_array((int) $vehicleId, $unavailable, true))) {
            // The tuk tuk they picked from the fleet page isn't covered by
            // this package, or is already booked for these dates — drop it
            // (the package step told them so), and BookingService falls
            // back to auto-assigning a free, eligible one.
            BookingFlowState::put(['vehicle_id' => null]);
        }

        BookingFlowState::put(['package_id' => $package->id, 'hold_key' => null]);

        return redirect()->route('booking.addons');
    }

    public function addons(): View|RedirectResponse
    {
        if (! BookingFlowState::hasDates() || ! BookingFlowState::hasPackageOrVehicle()) {
            return redirect()->route('booking.start');
        }

        $package = Package::query()->findOrFail(BookingFlowState::value('package_id'));
        $addons = $package->addons()->where('addons.is_active', true)->orderBy('addons.sort_order')->get();

        return view('booking::front.steps.addons', [
            'state' => BookingFlowState::get(),
            'package' => $package,
            'addons' => $addons,
        ]);
    }

    public function storeAddons(StepAddonsRequest $request): RedirectResponse
    {
        if (! BookingFlowState::hasDates() || ! BookingFlowState::hasPackageOrVehicle()) {
            return redirect()->route('booking.start');
        }

        $data = $request->validated();

        BookingFlowState::put([
            'addons' => array_filter($data['addons'] ?? [], fn (int $qty) => $qty > 0),
            'coupon_code' => $data['coupon_code'] ?? null,
        ]);

        return redirect()->route('booking.details');
    }

    public function details(): View|RedirectResponse
    {
        if (! BookingFlowState::hasDates() || ! BookingFlowState::hasPackageOrVehicle()) {
            return redirect()->route('booking.start');
        }

        return view('booking::front.steps.details', [
            'state' => BookingFlowState::get(),
            'isStay' => BookingFlowState::isStay(),
            'countries' => Countries::all(),
        ]);
    }

    public function storeDetails(StepDriverDetailsRequest $request): RedirectResponse
    {
        if (! BookingFlowState::hasDates() || ! BookingFlowState::hasPackageOrVehicle()) {
            return redirect()->route('booking.start');
        }

        BookingFlowState::put($request->validated());

        return redirect()->route('booking.review');
    }

    public function review(): View|RedirectResponse
    {
        if (! $this->readyForReview()) {
            return redirect()->route('booking.start');
        }

        if ($redirect = $this->invalidFlowRedirect()) {
            return $redirect;
        }

        // One idempotency key per checkout, created before the confirm form
        // is shown: a double-clicked "Confirm" sends it twice, and both
        // requests then resolve to the same booking (see confirm()).
        if (! is_string(BookingFlowState::value('hold_key'))) {
            BookingFlowState::put(['hold_key' => (string) Str::uuid()]);
        }

        $state = BookingFlowState::get();

        return view('booking::front.steps.review', [
            'state' => $state,
            'isStay' => BookingFlowState::isStay(),
            'package' => Package::query()->findOrFail($state['package_id']),
            'price' => $this->calculatePrice($state),
        ]);
    }

    /**
     * AJAX/JSON price recalculation (brief §4's explicit requirement for
     * the review step) — e.g. applying a coupon code without a full page
     * reload. Prices are always recomputed from scratch server-side here;
     * nothing the client sends is trusted as a total (brief §7).
     */
    public function recalculate(Request $request): JsonResponse
    {
        if (! $this->readyForReview()) {
            return response()->json(['message' => __('core::front.booking_session_expired')], 422);
        }

        $couponCode = $request->string('coupon_code')->trim()->value();
        $couponCode = $couponCode !== '' ? mb_strtoupper($couponCode) : null;

        $state = [...BookingFlowState::get(), 'coupon_code' => $couponCode];

        if ($couponCode !== null) {
            $coupon = Coupon::query()->where('code', $couponCode)->active()->first();

            if ($coupon === null || ! $coupon->isValidFor($this->daysFromState(), CarbonImmutable::parse($state['start_date']))) {
                return response()->json(['message' => __('core::front.booking_coupon_invalid')], 422);
            }
        }

        BookingFlowState::put(['coupon_code' => $couponCode]);

        return response()->json($this->calculatePrice($state)->toArray());
    }

    public function confirm(Request $request): RedirectResponse
    {
        if (! $this->readyForReview()) {
            return redirect()->route('booking.start');
        }

        $request->validate([
            'terms_accepted' => ['accepted'],
        ]);

        // The session may be stale (dates now in the past, package since
        // deactivated) or crafted (?package= on the start URL skips the
        // package step's own checks) — re-validate before booking anything.
        if ($redirect = $this->invalidFlowRedirect()) {
            return $redirect;
        }

        $state = BookingFlowState::get();
        $price = $this->calculatePrice($state);
        $holdKey = is_string($state['hold_key'] ?? null) ? $state['hold_key'] : (string) Str::uuid();

        // The review page showed a discounted total; if the coupon stopped
        // applying since (limit reached, expired), never book the higher
        // price silently — send them back to see the updated total.
        if (! empty($state['coupon_code']) && $price->couponCode === null) {
            return $this->couponNoLongerValid();
        }

        $addonSelections = array_map(
            fn (int $addonId, int $qty) => ['addon_id' => $addonId, 'quantity' => $qty],
            array_keys($state['addons'] ?? []),
            array_values($state['addons'] ?? []),
        );

        $customerData = [
            'email' => $state['email'],
            'full_name' => trim($state['first_name'].' '.$state['last_name']),
            'phone' => $state['phone'] ?? null,
            'nationality' => $state['nationality'] ?? null,
            'passport_number' => $state['passport_number'] ?? null,
            'locale_preference' => app()->getLocale(),
            'pickup_type' => $state['pickup_type'],
            'business_location_id' => $state['business_location_id'] ?? null,
            'delivery_zone_id' => $state['delivery_zone_id'] ?? null,
            'has_international_permit' => (bool) ($state['has_international_permit'] ?? false),
            'special_requests' => $state['special_requests'] ?? null,
        ];

        try {
            $booking = $this->bookings->createManualBooking(
                holdData: [
                    'hold_key' => $holdKey,
                    'start_at' => $state['start_date'],
                    'end_at' => $state['end_date'],
                    'vehicle_id' => $state['vehicle_id'] ?? null,
                    'package_id' => $state['package_id'],
                    'customer_session_id' => $request->session()->getId(),
                ],
                customerData: $customerData,
                price: $price,
                addonSelections: $addonSelections,
            );
        } catch (NoVehicleAvailableException|UniqueConstraintViolationException $exception) {
            // A double-clicked "Confirm": the other request (same hold key)
            // already holds — or has already booked — the vehicle, so this
            // one lost the race to *itself*. confirmHold() is idempotent and
            // waits on that hold's row lock, so it returns the very same
            // booking instead of an error or a second booking.
            $ownHold = BookingHold::query()->where('hold_key', $holdKey)->first();

            if ($ownHold === null) {
                if ($exception instanceof UniqueConstraintViolationException) {
                    throw $exception;
                }

                return redirect()->route('booking.package')
                    ->withErrors(['package_id' => __(BookingFlowState::isStay() ? 'core::front.booking_no_unit_available_stay' : 'core::front.booking_no_vehicle_available')]);
            }

            $booking = $this->bookings->confirmHold($ownHold, $customerData, $price, $addonSelections);
        } catch (CouponUnavailableException) {
            return $this->couponNoLongerValid();
        }

        BookingFlowState::clear();

        return redirect()->route('booking.confirmation', ['reference' => $booking->reference]);
    }

    public function confirmation(string $locale, string $reference): View
    {
        $booking = $this->findBookingOrFail($reference, ['vehicle.category', 'package', 'addons.addon']);
        $review = Review::query()->where('booking_id', $booking->id)->first();

        return view('booking::front.steps.confirmation', [
            'booking' => $booking,
            'review' => $review,
            'canReview' => $review === null && in_array($booking->status, self::REVIEWABLE_STATUSES, true),
        ]);
    }

    public function status(string $locale, string $reference): View
    {
        $booking = $this->findBookingOrFail($reference, ['vehicle.category', 'package']);

        return view('booking::front.steps.status', ['booking' => $booking]);
    }

    /**
     * Downloadable PDF receipt. Reachable by anyone holding the reference,
     * exactly like the confirmation page itself — the non-sequential
     * reference is the access token (see BookingReference).
     */
    public function receipt(string $locale, string $reference): Response
    {
        $booking = $this->findBookingOrFail($reference, ['customer', 'vehicle.category', 'package', 'businessLocation', 'deliveryZone']);
        $statusUrl = route('booking.status', ['locale' => $locale, 'reference' => $booking->reference]);
        $statusQrCode = base64_encode((new Writer(new ImageRenderer(new RendererStyle(240, 2), new SvgImageBackEnd())))
            ->writeString($statusUrl));

        return Pdf::loadView('booking::pdf.receipt', ['booking' => $booking, 'statusQrCode' => $statusQrCode])
            ->setPaper('a4')
            ->download('receipt-'.$booking->reference.'.pdf');
    }

    /**
     * Star rating + short comment left from the confirmation page. One
     * review per booking; it lands unapproved in the admin Reviews screen
     * and only shows publicly once a staff member approves it.
     */
    public function storeFeedback(Request $request, string $locale, string $reference): RedirectResponse
    {
        $booking = $this->findBookingOrFail($reference, ['customer']);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        if (! in_array($booking->status, self::REVIEWABLE_STATUSES, true)
            || Review::query()->where('booking_id', $booking->id)->exists()) {
            return redirect()->route('booking.confirmation', ['reference' => $booking->reference]);
        }

        $this->recordReview($booking, $data);

        return redirect()
            ->to(route('booking.confirmation', ['reference' => $booking->reference]).'#review')
            ->with('review_submitted', true);
    }

    public function storeHomepageFeedback(Request $request, string $locale): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);
        $reference = strtoupper(trim($data['reference']));
        $homeUrl = route('home', ['locale' => $locale]).'#feedback';

        if (! BookingReference::looksValid($reference)) {
            return redirect()->to($homeUrl)
                ->withErrors(['reference' => __('core::front.review_booking_unavailable')])
                ->withInput();
        }

        $booking = Booking::query()->with('customer')->where('reference', $reference)->first();

        if (! $booking
            || ! in_array($booking->status, self::REVIEWABLE_STATUSES, true)
            || Review::query()->where('booking_id', $booking->id)->exists()) {
            return redirect()->to($homeUrl)
                ->withErrors(['reference' => __('core::front.review_booking_unavailable')])
                ->withInput();
        }

        $this->recordReview($booking, $data);

        return redirect()->to($homeUrl)->with('review_submitted', true);
    }

    /**
     * @param  array{rating: int|string, comment?: string|null}  $data
     */
    private function recordReview(Booking $booking, array $data): void
    {
        // Public display name: first name + last initial, never the full
        // name a customer typed for their rental paperwork.
        $nameParts = preg_split('/\s+/', trim((string) $booking->customer?->full_name)) ?: [];
        $displayName = trim(($nameParts[0] ?? '').(count($nameParts) > 1 ? ' '.mb_substr(end($nameParts), 0, 1).'.' : ''));

        $nationality = $booking->customer?->nationality;

        Review::query()->create([
            'booking_id' => $booking->id,
            'customer_name' => $displayName !== '' ? $displayName : __('core::front.review_anonymous'),
            'country' => is_string($nationality) && mb_strlen($nationality) === 2 ? $nationality : null,
            'rating' => (int) $data['rating'],
            'content' => trim((string) ($data['comment'] ?? '')),
            'is_approved' => false,
        ]);
    }

    /**
     * @param  string[]  $with
     */
    private function findBookingOrFail(string $reference, array $with = []): Booking
    {
        if (! BookingReference::looksValid($reference)) {
            throw new NotFoundHttpException();
        }

        return Booking::query()->where('reference', $reference)->with($with)->first()
            ?? throw new NotFoundHttpException();
    }

    /**
     * Arriving from a stay's or a package's page (?vehicle=, ?package=, or
     * ?kind=stay) decides whether this is a tuk tuk rental or a stay.
     * Switching kind drops earlier choices: a stored end_date means the
     * return day for one and the last night for the other.
     */
    private function switchKindFor(Request $request): void
    {
        $requested = match (true) {
            $request->filled('package') => Package::query()->find($request->integer('package'))?->kind,
            $request->filled('vehicle') => Vehicle::query()->with('category')->find($request->integer('vehicle'))?->kind(),
            default => $request->query('kind'),
        };

        if (! in_array($requested, VehicleCategory::KINDS, true) || $requested === BookingFlowState::kind()) {
            return;
        }

        BookingFlowState::put([
            'kind' => $requested,
            'package_id' => null,
            'vehicle_id' => null,
            'start_date' => null,
            'end_date' => null,
            'hold_key' => null,
        ]);
    }

    private function notEligibleMessage(): string
    {
        return __(BookingFlowState::isStay() ? 'core::front.booking_package_not_eligible_stay' : 'core::front.booking_package_not_eligible');
    }

    private function couponNoLongerValid(): RedirectResponse
    {
        BookingFlowState::put(['coupon_code' => null]);

        return redirect()->route('booking.review')
            ->withErrors(['coupon_code' => __('core::front.booking_coupon_no_longer_valid')]);
    }

    /**
     * Server-side re-validation of everything earlier steps accepted, run
     * at review and again at confirm. Returns where to send the customer
     * (with a translated message) when something no longer holds, or null.
     */
    private function invalidFlowRedirect(): ?RedirectResponse
    {
        [$start, $end] = $this->datesFromState();
        $days = $this->daysFromState();

        if ($start->lt(CarbonImmutable::today())
            || $start->gt(CarbonImmutable::today()->addDays((int) config('availability.maximum_advance_days')))
            || $end->lt($start)
            || $days > (int) config('booking.max_rental_days')) {
            return redirect()->route('booking.start')
                ->withErrors(['start_date' => __('core::front.booking_dates_no_longer_valid')]);
        }

        $package = Package::query()->active()->currentlyValid()->ofKind(BookingFlowState::kind())->find(BookingFlowState::value('package_id'));

        if ($package === null || $days < $package->min_days || ($package->max_days !== null && $days > $package->max_days)) {
            BookingFlowState::put(['package_id' => null, 'hold_key' => null]);

            return redirect()->route('booking.package')
                ->withErrors(['package_id' => $this->notEligibleMessage()]);
        }

        $vehicleId = BookingFlowState::value('vehicle_id');

        if ($vehicleId !== null && ! in_array((int) $vehicleId, $package->eligibleVehicleIds(), true)) {
            // Same fallback as storePackage(): auto-assign an eligible one.
            BookingFlowState::put(['vehicle_id' => null]);
        }

        return null;
    }

    /**
     * Free eligible vehicles per package for the selected dates, for the
     * package step's "fully booked" / "only N left" labels. Expects the
     * packages' `vehicles` and `categories` eager-loaded: the whole list
     * costs a fixed handful of queries however many packages there are.
     *
     * @param  Collection<int, Package>  $packages
     * @param  int[]  $unavailable  filled with every busy vehicle id seen
     * @return array<int, int>  package id => free vehicle count
     */
    private function freeVehicleCounts(Collection $packages, CarbonImmutable $start, CarbonImmutable $end, array &$unavailable): array
    {
        $activeFleet = Vehicle::query()->active()->ofKind(BookingFlowState::kind())->pluck('category_id', 'id')->all();
        $eligible = $packages->mapWithKeys(fn (Package $package) => [$package->id => $package->eligibleVehicleIdsAmong($activeFleet)]);

        $vehicleId = BookingFlowState::value('vehicle_id');
        $allIds = $eligible->flatten()->push(...($vehicleId !== null ? [(int) $vehicleId] : []))->unique()->values()->all();
        $unavailable = $this->availability->unavailableVehicleIds($allIds, $start, $end);

        return $eligible->map(fn (array $ids) => count(array_diff($ids, $unavailable)))->all();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function datesFromState(): array
    {
        return [
            CarbonImmutable::parse(BookingFlowState::value('start_date'))->startOfDay(),
            CarbonImmutable::parse(BookingFlowState::value('end_date'))->startOfDay(),
        ];
    }

    private function readyForReview(): bool
    {
        return BookingFlowState::hasDates()
            && BookingFlowState::hasPackageOrVehicle()
            && BookingFlowState::value('email') !== null;
    }

    private function daysFromState(): int
    {
        $start = CarbonImmutable::parse(BookingFlowState::value('start_date'));
        $end = CarbonImmutable::parse(BookingFlowState::value('end_date'));

        // Carbon's diffInDays() returns float — see BookingController (Admin)
        // for the same explicit-cast note.
        return (int) $start->diffInDays($end) + 1;
    }

    private function calculatePrice(array $state): PriceBreakdown
    {
        $package = Package::query()->findOrFail($state['package_id']);
        $start = CarbonImmutable::parse($state['start_date']);
        $days = $this->daysFromState();

        $addonSelections = array_map('intval', $state['addons'] ?? []);

        $coupon = ! empty($state['coupon_code'])
            ? Coupon::query()->where('code', $state['coupon_code'])->active()->first()
            : null;

        $deliveryZone = ($state['pickup_type'] ?? null) === 'delivery' && ! empty($state['delivery_zone_id'])
            ? DeliveryZone::query()->find($state['delivery_zone_id'])
            : null;

        return $this->pricing->calculate($package, $start, $days, $addonSelections, $coupon, $deliveryZone);
    }
}
