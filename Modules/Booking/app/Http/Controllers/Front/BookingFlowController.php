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
use Modules\Admin\Models\BlockedIp;
use Modules\Availability\Models\BusinessLocation;
use Modules\Availability\Models\DeliveryZone;
use Modules\Availability\Models\TransferArea;
use Modules\Availability\Services\AvailabilityService;
use Modules\Booking\Exceptions\CouponUnavailableException;
use Modules\Booking\Exceptions\HoldExpiredException;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Http\Requests\Front\StepAddonsRequest;
use Modules\Booking\Http\Requests\Front\StepDatesRequest;
use Modules\Booking\Http\Requests\Front\StepDriverDetailsRequest;
use Modules\Booking\Http\Requests\Front\StepPackageRequest;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingHold;
use Modules\Booking\Services\BookingService;
use Modules\Booking\Services\TransferAvailability;
use Modules\Booking\Support\BookingFlowState;
use Modules\Booking\Support\BookingReference;
use Modules\Booking\Support\HourlySchedule;
use Modules\Booking\Support\TransferSchedule;
use Modules\CMS\Models\Review;
use Modules\Core\Support\Countries;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;
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
        private readonly TransferAvailability $transfers,
    ) {
    }

    public function start(Request $request): View
    {
        $this->switchKindFor($request);
        $this->defaultLine();

        // Activity packages (surfing…) are booked by message, not here.
        if ($request->filled('package') && Package::query()->bookable()->inVisibleCategory()->whereKey($request->integer('package'))->exists()) {
            BookingFlowState::put(['package_id' => $request->integer('package')]);
        }

        // Customers never pick a vehicle: rentals and airport pickups get
        // one assigned. Only a stay keeps the cabana or room they opened.
        if (! BookingFlowState::isStay()) {
            BookingFlowState::put(['vehicle_id' => null]);
        } elseif ($request->filled('vehicle')) {
            BookingFlowState::put(['vehicle_id' => $request->integer('vehicle')]);
        }

        return view('booking::front.steps.dates', [
            'state' => BookingFlowState::get(),
            'isStay' => BookingFlowState::isStay(),
            'isTransfer' => BookingFlowState::isTransfer(),
            'lines' => $this->bookableLines(),
            'currentLineId' => BookingFlowState::lineId(),
            'office' => BusinessLocation::office(),
            'transferAreas' => BookingFlowState::isTransfer() ? TransferArea::query()->active()->ordered()->get() : collect(),
        ]);
    }

    public function storeDates(StepDatesRequest $request): RedirectResponse
    {
        $this->defaultLine();
        BookingFlowState::put([...$request->flowData(), 'hold_key' => null]);

        return redirect()->route('booking.package');
    }

    public function package(): View|RedirectResponse
    {
        if (! BookingFlowState::hasDates()) {
            return redirect()->route('booking.start');
        }

        $days = $this->daysFromState();

        $packages = $this->flowPackages()
            ->where('min_days', '<=', $days)
            ->where(fn ($q) => $q->whereNull('max_days')->orWhere('max_days', '>=', $days))
            ->with(['pricingTiers', 'images', 'vehicles:id,status', 'categories:id'])
            ->orderBy('sort_order')
            ->get();

        $vehicleId = BookingFlowState::value('vehicle_id');
        [$start, $end] = $this->datesFromState();
        $unavailable = [];
        // Rentals are always bookable (see takesEveryBooking()); stays and
        // airport pickups still can't be double-booked, so a full package is
        // disabled — but no "N left" counts are shown for any of them.
        $checksAvailability = ! $this->takesEveryBooking();
        $freeCounts = $checksAvailability ? $this->freeVehicleCounts($packages, $start, $end, $unavailable) : [];

        return view('booking::front.steps.package', [
            'state' => BookingFlowState::get(),
            'isStay' => BookingFlowState::isStay(),
            'isTransfer' => BookingFlowState::isTransfer(),
            'packages' => $packages,
            'days' => $days,
            'checksAvailability' => $checksAvailability,
            'freeCounts' => $freeCounts,
            'hourlyStartTimes' => $packages->filter->isHourly()->mapWithKeys(fn (Package $p) => [$p->id => HourlySchedule::startTimes($p)])->all(),
            'hourlyLengths' => $packages->filter->isHourly()->mapWithKeys(fn (Package $p) => [$p->id => HourlySchedule::lengths($p)])->all(),
            'preselectedVehicle' => $vehicleId !== null ? Vehicle::query()->find($vehicleId) : null,
            'preselectedVehicleBooked' => $vehicleId !== null && in_array((int) $vehicleId, $unavailable, true),
        ]);
    }

    public function storePackage(StepPackageRequest $request): RedirectResponse
    {
        if (! BookingFlowState::hasDates()) {
            return redirect()->route('booking.start');
        }

        $package = $this->flowPackages()->findOrFail($request->validated('package_id'));
        $days = $this->daysFromState();

        if ($days < $package->min_days || ($package->max_days !== null && $days > $package->max_days)) {
            return back()->withErrors(['package_id' => $this->notEligibleMessage()]);
        }

        if (! $this->takesEveryBooking()) {
            [$start, $end] = $this->datesFromState();
            $eligibleIds = $package->eligibleVehicleIds();
            $unavailable = $this->unavailableIds($eligibleIds, $start, $end);

            if (array_diff($eligibleIds, $unavailable) === []) {
                return back()->withErrors(['package_id' => __('core::front.booking_package_fully_booked_error')]);
            }

            $vehicleId = BookingFlowState::value('vehicle_id');

            if ($vehicleId !== null
                && (! in_array((int) $vehicleId, $eligibleIds, true) || in_array((int) $vehicleId, $unavailable, true))) {
                // The cabana they opened isn't covered by this package, or
                // is already booked for these dates — drop it (the package
                // step told them so), and BookingService falls back to
                // auto-assigning a free, eligible one.
                BookingFlowState::put(['vehicle_id' => null]);
            }
        }

        [$startTime, $hours] = $package->isHourly() ? $request->hourlyChoice() : [null, null];

        BookingFlowState::put([
            'package_id' => $package->id,
            'start_time' => $startTime,
            'hours' => $hours !== null ? (int) $hours : null,
            'hold_key' => null,
        ]);

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
            'isTransfer' => BookingFlowState::isTransfer(),
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

        $package = Package::query()->findOrFail($state['package_id']);

        return view('booking::front.steps.review', [
            'state' => $state,
            'isStay' => BookingFlowState::isStay(),
            'isTransfer' => BookingFlowState::isTransfer(),
            'transferArea' => $this->transferAreaFromState(),
            'package' => $package,
            'hourlyRange' => $package->isHourly() ? HourlySchedule::range($state['start_date'], $state['start_time'], (int) $state['hours']) : null,
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

        // Staff blocked this IP from a spam booking (admin booking page).
        // Point them to a person rather than failing silently: mobile
        // networks share IPs, so a real tourist can land on a blocked one.
        if (BlockedIp::isBlocked((string) $request->ip())) {
            return redirect()->route('booking.review')
                ->withErrors(['booking' => __('core::front.booking_blocked')]);
        }

        // The session may be stale (dates now in the past, package since
        // deactivated) or crafted (?package= on the start URL skips the
        // package step's own checks) — re-validate before booking anything.
        if ($redirect = $this->invalidFlowRedirect()) {
            return $redirect;
        }

        $state = BookingFlowState::get();
        $price = $this->calculatePrice($state);
        $holdKey = is_string($state['hold_key'] ?? null) ? $state['hold_key'] : (string) Str::uuid();

        // An hourly rental keeps its real times on the booking; slots are
        // still reserved by whole day (BookingService::createHold()). An
        // airport pickup reserves exactly its trip's time slot.
        [$startAt, $endAt] = match (true) {
            BookingFlowState::isTransfer() => array_map(fn (CarbonImmutable $at) => $at->toDateTimeString(), $this->datesFromState()),
            $price->hours !== null => array_map(fn (CarbonImmutable $at) => $at->toDateTimeString(), HourlySchedule::range($state['start_date'], $state['start_time'], $price->hours)),
            default => [$state['start_date'], $state['end_date']],
        };
        $isTransfer = BookingFlowState::isTransfer();

        // The review page showed a discounted total; if the coupon stopped
        // applying since (limit reached, expired), never book the higher
        // price silently — send them back to see the updated total.
        if (! empty($state['coupon_code']) && $price->couponCode === null) {
            return $this->couponNoLongerValid();
        }

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
            'transfer_direction' => $isTransfer ? $state['transfer_direction'] : null,
            'flight_number' => $isTransfer ? $state['flight_number'] : null,
            'transfer_address' => $isTransfer ? $state['transfer_address'] : null,
            'transfer_area_id' => $isTransfer ? (int) $state['transfer_area_id'] : null,
            'has_international_permit' => (bool) ($state['has_international_permit'] ?? false),
            'special_requests' => $state['special_requests'] ?? null,
        ];
        $source = ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()];

        try {
            $booking = $this->bookings->createManualBooking(
                holdData: [
                    'hold_key' => $holdKey,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'vehicle_id' => BookingFlowState::isStay() ? ($state['vehicle_id'] ?? null) : null,
                    'package_id' => $state['package_id'],
                    'customer_session_id' => $request->session()->getId(),
                    // A rental is never refused: with every vehicle out it is
                    // taken unassigned and staff assign one later.
                    'allow_unassigned' => $this->takesEveryBooking(),
                ],
                customerData: $customerData,
                price: $price,
                source: $source,
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
                    ->withErrors(['package_id' => __(match (true) {
                        BookingFlowState::isStay() => 'core::front.booking_no_unit_available_stay',
                        $isTransfer => 'core::front.booking_no_transfer_available',
                        default => 'core::front.booking_no_vehicle_available',
                    })]);
            }

            $booking = $this->bookings->confirmHold($ownHold, $customerData, $price, $source);
        } catch (CouponUnavailableException) {
            return $this->couponNoLongerValid();
        } catch (HoldExpiredException) {
            // This checkout's hold_key points at a hold that was released
            // or expired (an earlier confirm failed). Retrying with the same
            // key would fail the same way every time — start a fresh one.
            BookingFlowState::put(['hold_key' => null]);

            return redirect()->route('booking.review')
                ->withErrors(['package_id' => __('core::front.booking_hold_expired')]);
        }

        BookingFlowState::clear();

        return redirect()->route('booking.confirmation', ['reference' => $booking->reference]);
    }

    public function confirmation(string $locale, string $reference): View
    {
        $booking = $this->findBookingOrFail($reference, ['vehicle.category', 'package', 'addons.addon', 'transferArea']);
        $review = Review::query()->where('booking_id', $booking->id)->first();

        return view('booking::front.steps.confirmation', [
            'booking' => $booking,
            'review' => $review,
            'canReview' => $review === null && in_array($booking->status, self::REVIEWABLE_STATUSES, true),
        ]);
    }

    public function status(string $locale, string $reference): View
    {
        $booking = $this->findBookingOrFail($reference, ['vehicle.category', 'package', 'transferArea']);

        return view('booking::front.steps.status', ['booking' => $booking]);
    }

    /**
     * Downloadable PDF receipt. Reachable by anyone holding the reference,
     * exactly like the confirmation page itself — the non-sequential
     * reference is the access token (see BookingReference).
     */
    public function receipt(string $locale, string $reference): Response
    {
        $booking = $this->findBookingOrFail($reference, ['customer', 'vehicle.category', 'package', 'businessLocation', 'deliveryZone', 'transferArea']);
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
     * Arriving from a line switch, a unit's or a package's page (?line=,
     * ?package=, ?vehicle=, or the older ?kind=) decides what is booked:
     * which product line, and with it the kind (rental, stay or airport
     * pickup). Switching kind drops earlier choices — a stored end_date
     * means the return day for a rental, the last night for a stay and the
     * trip day for a pickup; switching only the line (Tuk Tuk → Vehicle
     * Rent) keeps the dates but drops the package and unit.
     */
    private function switchKindFor(Request $request): void
    {
        [$kind, $lineId] = match (true) {
            $request->filled('line') => $this->lineFromRequest($request->integer('line')),
            $request->filled('package') => (function () use ($request): array {
                $package = Package::query()->find($request->integer('package'));

                return [$package?->kind, $package?->product_category_id];
            })(),
            $request->filled('vehicle') => (function () use ($request): array {
                $vehicle = Vehicle::query()->with('category')->find($request->integer('vehicle'));

                return [$vehicle?->kind(), $vehicle?->category?->product_category_id];
            })(),
            default => [$request->query('kind'), null],
        };

        if (! in_array($kind, VehicleCategory::KINDS, true)) {
            return;
        }

        if ($kind !== BookingFlowState::kind()) {
            BookingFlowState::put([
                'kind' => $kind,
                'line_id' => $lineId,
                'package_id' => null,
                'vehicle_id' => null,
                'start_date' => null,
                'end_date' => null,
                'hold_key' => null,
            ]);

            return;
        }

        if ($lineId !== null && (int) $lineId !== BookingFlowState::lineId()) {
            BookingFlowState::put(['line_id' => (int) $lineId, 'package_id' => null, 'vehicle_id' => null, 'hold_key' => null]);
        }
    }

    /**
     * Day rentals (Tuk Tuk, Vehicle Rent) are taken whatever the fleet's
     * availability: the customer never sees counts or "fully booked", and a
     * booking with no free vehicle is saved unassigned for staff to sort
     * out. Stays and airport pickups are still never double-booked.
     */
    private function takesEveryBooking(): bool
    {
        return BookingFlowState::kind() === VehicleCategory::KIND_VEHICLE;
    }

    /**
     * No line chosen yet (straight to /booking/start, or an old session):
     * the first line of the flow's kind — Tuk Tuk for rentals — so the
     * package step never mixes tuk tuk and Vehicle Rent packages.
     */
    private function defaultLine(): void
    {
        if (BookingFlowState::lineId() !== null) {
            return;
        }

        $lineId = $this->bookableLines()->firstWhere('kind', BookingFlowState::kind())?->id;

        if ($lineId !== null) {
            BookingFlowState::put(['line_id' => $lineId]);
        }
    }

    /** @return array{0: string|null, 1: int|null} */
    private function lineFromRequest(int $lineId): array
    {
        $line = $this->bookableLines()->firstWhere('id', $lineId);

        return [$line?->kind, $line?->id];
    }

    /**
     * The lines the wizard's switch offers: active, visible, and booked
     * online (activities like Kite & Surfing are booked by message).
     *
     * @return \Illuminate\Support\Collection<int, ProductCategory>
     */
    private function bookableLines(): \Illuminate\Support\Collection
    {
        return ProductCategory::query()
            ->active()
            ->whereIn('kind', VehicleCategory::KINDS)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Packages the wizard may book right now: of the flow's kind, and of
     * its product line when one is chosen (so Vehicle Rent never lists
     * tuk tuk packages).
     *
     * @return \Illuminate\Database\Eloquent\Builder<Package>
     */
    private function flowPackages(): \Illuminate\Database\Eloquent\Builder
    {
        return Package::query()
            ->active()
            ->currentlyValid()
            ->inVisibleCategory()
            ->ofKind(BookingFlowState::kind())
            ->when(BookingFlowState::lineId() !== null, fn ($q) => $q->where('product_category_id', BookingFlowState::lineId()));
    }

    /**
     * Busy vehicles among $vehicleIds: by time slot for an airport pickup,
     * by whole days otherwise. Advisory — BookingService re-checks under lock.
     *
     * @param  int[]  $vehicleIds
     * @return int[]
     */
    private function unavailableIds(array $vehicleIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return BookingFlowState::isTransfer()
            ? $this->transfers->unavailableVehicleIds($vehicleIds, $start, $end)
            : $this->availability->unavailableVehicleIds($vehicleIds, $start, $end);
    }

    private function transferAreaFromState(): ?TransferArea
    {
        $areaId = BookingFlowState::value('transfer_area_id');

        return BookingFlowState::isTransfer() && $areaId !== null
            ? TransferArea::query()->active()->find((int) $areaId)
            : null;
    }

    private function notEligibleMessage(): string
    {
        return __(BookingFlowState::isStay() ? 'core::front.booking_package_not_eligible_stay' : 'core::front.booking_package_not_eligible');
    }

    private function couponNoLongerValid(): RedirectResponse
    {
        // The failed confirm released its hold; a new key gets a new one.
        BookingFlowState::put(['coupon_code' => null, 'hold_key' => null]);

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

        if (BookingFlowState::isTransfer()) {
            // The area may have been switched off, or the pickup time come
            // too close, since step 1.
            $problem = $this->transferAreaFromState() === null
                ? __('core::front.booking_dates_no_longer_valid')
                : TransferSchedule::problem((string) BookingFlowState::value('start_date'), BookingFlowState::value('transfer_time'));

            if ($problem !== null) {
                BookingFlowState::put(['hold_key' => null]);

                return redirect()->route('booking.start')->withErrors(['transfer_time' => $problem]);
            }
        }

        $package = $this->flowPackages()->find(BookingFlowState::value('package_id'));

        if ($package === null || $days < $package->min_days || ($package->max_days !== null && $days > $package->max_days)) {
            BookingFlowState::put(['package_id' => null, 'hold_key' => null]);

            return redirect()->route('booking.package')
                ->withErrors(['package_id' => $this->notEligibleMessage()]);
        }

        if ($package->isHourly()) {
            $problem = HourlySchedule::problem($package, $start->toDateString(), BookingFlowState::value('start_time'), BookingFlowState::value('hours'));

            if ($problem !== null) {
                BookingFlowState::put(['hold_key' => null]);

                return redirect()->route('booking.package')->withErrors(['package_id' => $problem]);
            }
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
        $unitTypeLines = VehicleCategory::query()->ofKind(BookingFlowState::kind())->pluck('product_category_id', 'id')->all();
        $eligible = $packages->mapWithKeys(fn (Package $package) => [$package->id => $package->eligibleVehicleIdsAmong($activeFleet, $unitTypeLines)]);

        $vehicleId = BookingFlowState::value('vehicle_id');
        $allIds = $eligible->flatten()->push(...($vehicleId !== null ? [(int) $vehicleId] : []))->unique()->values()->all();
        $unavailable = $this->unavailableIds($allIds, $start, $end);

        return $eligible->map(fn (array $ids) => count(array_diff($ids, $unavailable)))->all();
    }

    /**
     * The booked range: whole days for a rental or stay; for an airport
     * pickup the real trip times (pickup time → pickup + area duration).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function datesFromState(): array
    {
        $area = $this->transferAreaFromState();

        if ($area !== null && is_string(BookingFlowState::value('transfer_time'))) {
            return TransferSchedule::range((string) BookingFlowState::value('start_date'), BookingFlowState::value('transfer_time'), $area);
        }

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

        return $this->pricing->calculate(
            $package,
            $start,
            $days,
            $addonSelections,
            $coupon,
            $deliveryZone,
            hours: $package->isHourly() ? (int) ($state['hours'] ?? 0) : null,
            transferArea: $this->transferAreaFromState(),
        );
    }
}
