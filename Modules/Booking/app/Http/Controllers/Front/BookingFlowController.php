<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Availability\Models\BusinessLocation;
use Modules\Availability\Models\DeliveryZone;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Http\Requests\Front\StepAddonsRequest;
use Modules\Booking\Http\Requests\Front\StepDatesRequest;
use Modules\Booking\Http\Requests\Front\StepDriverDetailsRequest;
use Modules\Booking\Http\Requests\Front\StepPackageRequest;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Booking\Support\BookingFlowState;
use Modules\Booking\Support\BookingReference;
use Modules\CMS\Models\Review;
use Modules\Core\Support\Countries;
use Modules\Fleet\Models\Vehicle;
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
    ) {
    }

    public function start(Request $request): View
    {
        if ($request->filled('package')) {
            BookingFlowState::put(['package_id' => $request->integer('package')]);
        }

        if ($request->filled('vehicle')) {
            BookingFlowState::put(['vehicle_id' => $request->integer('vehicle')]);
        }

        return view('booking::front.steps.dates', [
            'state' => BookingFlowState::get(),
            'locations' => BusinessLocation::query()->active()->get(),
            'deliveryZones' => DeliveryZone::query()->active()->get(),
        ]);
    }

    public function storeDates(StepDatesRequest $request): RedirectResponse
    {
        BookingFlowState::put($request->validated());

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
            ->where('min_days', '<=', $days)
            ->where(fn ($q) => $q->whereNull('max_days')->orWhere('max_days', '>=', $days))
            ->with(['pricingTiers', 'images'])
            ->orderBy('sort_order')
            ->get();

        $vehicleId = BookingFlowState::value('vehicle_id');

        return view('booking::front.steps.package', [
            'state' => BookingFlowState::get(),
            'packages' => $packages,
            'days' => $days,
            'preselectedVehicle' => $vehicleId !== null ? Vehicle::query()->find($vehicleId) : null,
        ]);
    }

    public function storePackage(StepPackageRequest $request): RedirectResponse
    {
        if (! BookingFlowState::hasDates()) {
            return redirect()->route('booking.start');
        }

        $package = Package::query()->active()->currentlyValid()->findOrFail($request->validated('package_id'));
        $days = $this->daysFromState();

        if ($days < $package->min_days || ($package->max_days !== null && $days > $package->max_days)) {
            return back()->withErrors(['package_id' => __('core::front.booking_package_not_eligible')]);
        }

        $vehicleId = BookingFlowState::value('vehicle_id');

        if ($vehicleId !== null && ! in_array((int) $vehicleId, $package->eligibleVehicleIds(), true)) {
            // The tuk tuk they picked from the fleet page isn't covered by
            // this package — drop it rather than silently ignoring it, so
            // BookingService falls back to auto-assigning an eligible one.
            BookingFlowState::put(['vehicle_id' => null]);
        }

        BookingFlowState::put(['package_id' => $package->id]);

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

        $state = BookingFlowState::get();

        return view('booking::front.steps.review', [
            'state' => $state,
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

        $state = BookingFlowState::get();
        $price = $this->calculatePrice($state);

        $addonSelections = array_map(
            fn (int $addonId, int $qty) => ['addon_id' => $addonId, 'quantity' => $qty],
            array_keys($state['addons'] ?? []),
            array_values($state['addons'] ?? []),
        );

        try {
            $booking = $this->bookings->createManualBooking(
                holdData: [
                    'hold_key' => (string) Str::uuid(),
                    'start_at' => $state['start_date'],
                    'end_at' => $state['end_date'],
                    'vehicle_id' => $state['vehicle_id'] ?? null,
                    'package_id' => $state['package_id'],
                    'customer_session_id' => $request->session()->getId(),
                ],
                customerData: [
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
                ],
                price: $price,
                addonSelections: $addonSelections,
            );
        } catch (NoVehicleAvailableException $exception) {
            return redirect()->route('booking.package')->withErrors(['package_id' => $exception->getMessage()]);
        }

        BookingFlowState::clear();

        return redirect()->route('booking.confirmation', ['reference' => $booking->reference]);
    }

    public function confirmation(string $locale, string $reference): View
    {
        $booking = $this->findBookingOrFail($reference, ['vehicle', 'package', 'addons.addon']);
        $review = Review::query()->where('booking_id', $booking->id)->first();

        return view('booking::front.steps.confirmation', [
            'booking' => $booking,
            'review' => $review,
            'canReview' => $review === null && in_array($booking->status, self::REVIEWABLE_STATUSES, true),
        ]);
    }

    /**
     * Downloadable PDF receipt. Reachable by anyone holding the reference,
     * exactly like the confirmation page itself — the non-sequential
     * reference is the access token (see BookingReference).
     */
    public function receipt(string $locale, string $reference): Response
    {
        $booking = $this->findBookingOrFail($reference, ['customer', 'vehicle', 'package', 'businessLocation', 'deliveryZone']);

        return Pdf::loadView('booking::pdf.receipt', ['booking' => $booking])
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

        return redirect()
            ->to(route('booking.confirmation', ['reference' => $booking->reference]).'#review')
            ->with('review_submitted', true);
    }

    /**
     * @param  string[]  $with
     */
    private function findBookingOrFail(string $reference, array $with = []): Booking
    {
        if (! BookingReference::looksValid($reference)) {
            throw new NotFoundHttpException;
        }

        return Booking::query()->where('reference', $reference)->with($with)->first()
            ?? throw new NotFoundHttpException;
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
