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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Admin\Models\BlockedIp;
use Modules\Availability\Models\DeliveryZone;
use Modules\Availability\Models\TransferArea;
use Modules\Availability\Services\AvailabilityService;
use Modules\Booking\Exceptions\HoldExpiredException;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Http\Requests\Front\StepAddonsRequest;
use Modules\Booking\Http\Requests\Front\StepDatesRequest;
use Modules\Booking\Http\Requests\Front\StepDriverDetailsRequest;
use Modules\Booking\Http\Requests\Front\StepOfferRequest;
use Modules\Booking\Http\Requests\Front\StepPackageRequest;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingHold;
use Modules\Booking\Services\BookingService;
use Modules\Booking\Services\OfferedBookingPlanner;
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
use Modules\Package\Models\PackageOffer;
use Modules\Package\Models\ProductCategory;
use Modules\Pricing\DataObjects\PriceBreakdown;
use Modules\Pricing\Services\PricingService;
use Modules\Pricing\Support\Money;
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
        private readonly OfferedBookingPlanner $offers,
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

        $lines = $this->bookableLines();

        return view('booking::front.steps.dates', [
            'state' => BookingFlowState::get(),
            'isStay' => BookingFlowState::isStay(),
            'isTransfer' => BookingFlowState::isTransfer(),
            'isTour' => BookingFlowState::isTour(),
            'lines' => $lines,
            'currentLineId' => BookingFlowState::lineId(),
            'currentLine' => $lines->firstWhere('id', BookingFlowState::lineId()),
            // What this service offers, before any dates are picked. The
            // package step still filters these by the chosen dates.
            'linePackages' => $this->flowPackages()->with(['pricingTiers', 'images'])->orderBy('sort_order')->get(),
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
            ->with(['pricingTiers', 'images', 'liveOffers', 'vehicles:id,status', 'categories:id'])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Package $package) => $package->hasPriceForDays($days))
            ->values();

        $vehicleId = BookingFlowState::value('vehicle_id');
        [$start, $end] = $this->datesFromState();
        $unavailable = [];
        // Tuk tuks are always bookable (see lineHasUnlimitedUnits()). Registered
        // units can't be double-booked, so a full package is disabled — but
        // no "N left" counts are shown for any of them.
        $checksAvailability = ! $this->lineHasUnlimitedUnits();
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

        if ($days < $package->min_days || ($package->max_days !== null && $days > $package->max_days) || ! $package->hasPriceForDays($days)) {
            return back()->withErrors(['package_id' => $this->notEligibleMessage()]);
        }

        if ($package->needsTransferArea() && $this->transferAreaFromState() === null) {
            return redirect()->route('booking.start')->withErrors(['transfer_area_id' => __('core::front.transfer_area_needed')]);
        }

        if (! $package->hasUnlimitedUnits()) {
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
            'people' => $package->isGroupPriced() ? $request->peopleChoice() : null,
            // An offer belongs to the package it was taken on.
            'offer' => null,
            'extras' => $this->extrasWithout($package->id),
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
            'isTour' => BookingFlowState::isTour(),
            'countries' => Countries::all(),
            // A package that needs the licence asks how many people will drive (each pays the permit fee).
            'licencePackage' => Package::query()->whereKey(BookingFlowState::value('package_id'))->where('requires_licence_documents', true)->first(),
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
        $chosenOffer = $this->chosenOffer();
        $price = $this->calculatePrice($state);
        $offeredPrice = $chosenOffer !== null ? $this->offers->plan($chosenOffer->offeredPackage, $state['offer'])['price'] : null;
        $availableOffers = $chosenOffer === null ? $package->availableOffers() : collect();
        $extras = $state['extras'] ?? [];
        $addedPackages = $this->addablePackages()->whereKey(array_keys($extras))->with('images')->orderBy('id')->get()
            ->map(fn (Package $added) => ['package' => $added, 'data' => $extras[$added->id], 'price' => $this->addedPlan($added, $extras[$added->id])['price']]);
        $throughOffers = $this->packagesAddedThroughOffers();
        // The saving this package earns on another one, if that one isn't in the checkout yet.
        $offersOnOthers = $package->offersOnOtherPackages()
            ->reject(fn (PackageOffer $offer) => array_key_exists($offer->package_id, $extras) || in_array($offer->package_id, $throughOffers, true))
            ->values();

        // What the customer now sees, for confirm() to compare with.
        BookingFlowState::put(['extra_savings' => $this->extraSavings()]);

        // Every bill on this page. EUR only if each of them has it.
        $bills = collect([$price, $offeredPrice, ...$addedPackages->pluck('price')])->filter();
        $billsEur = $bills->map(fn (PriceBreakdown $bill) => $bill->eur['total'] ?? null);

        return view('booking::front.steps.review', [
            'needsLicence' => $this->checkoutNeedsLicence(),
            'state' => $state,
            'isStay' => BookingFlowState::isStay(),
            'isTransfer' => BookingFlowState::isTransfer(),
            'transferArea' => $this->transferAreaFromState(),
            'package' => $package,
            'hourlyRange' => $package->isHourly() ? HourlySchedule::range($state['start_date'], $state['start_time'], (int) $state['hours']) : null,
            'price' => $price,
            'availableOffers' => $availableOffers,
            // This customer's bill if they take each offer, for the before and after price.
            'offerPrices' => $availableOffers->mapWithKeys(fn (PackageOffer $offer) => [$offer->id => $this->pricing->withOffer($package, $price, $offer)]),
            'chosenOffer' => $chosenOffer,
            'offeredPrice' => $offeredPrice,
            'addedPackages' => $addedPackages,
            'offersOnOthers' => $offersOnOthers,
            // A package with a saving has its own card above, so the saving is never missed.
            'addablePackages' => $this->addablePackages()
                ->whereNotIn('id', [...$throughOffers, ...$offersOnOthers->pluck('package_id'), ...array_keys($extras)])
                ->with(['pricingTiers', 'images', 'productCategory'])
                ->orderBy('sort_order')
                ->get()
                ->sortBy(fn (Package $addable) => $addable->productCategory?->sort_order)
                ->groupBy(fn (Package $addable) => (string) $addable->productCategory?->name),
            'allBookingsTotal' => $bills->count() > 1
                ? Money::dual((float) $bills->sum('total'), $billsEur->containsStrict(null) ? null : (float) $billsEur->sum())
                : null,
        ]);
    }

    /** The review step's "add this package and save": only what that package needs. */
    public function offer(string $locale, string $offer): View|RedirectResponse
    {
        if (! $this->readyForReview()) {
            return redirect()->route('booking.start');
        }

        $packageOffer = $this->availableOffer((int) $offer);

        if ($packageOffer === null) {
            return redirect()->route('booking.review');
        }

        $chosen = BookingFlowState::value('offer');

        // Picking the offer again: what they entered.
        return $this->addedPackageForm($packageOffer->offeredPackage, $packageOffer, is_array($chosen) && (int) ($chosen['offer_id'] ?? 0) === $packageOffer->id ? $chosen : null);
    }

    public function storeOffer(StepOfferRequest $request, string $locale, string $offer): RedirectResponse
    {
        if (! $this->readyForReview()) {
            return redirect()->route('booking.start');
        }

        $packageOffer = $this->availableOffer((int) $offer);

        if ($packageOffer !== null) {
            BookingFlowState::put([
                'offer' => ['offer_id' => $packageOffer->id, ...$request->offerData()],
                'extras' => $this->extrasWithout($packageOffer->offered_package_id),
                'hold_key' => null,
            ]);
        }

        return redirect()->route('booking.review');
    }

    public function removeOffer(): RedirectResponse
    {
        BookingFlowState::put(['offer' => null, 'hold_key' => null]);

        return redirect()->route('booking.review');
    }

    /** The review step's "add another package": only what that package needs. */
    public function addPackage(string $locale, string $package): View|RedirectResponse
    {
        if (! $this->readyForReview()) {
            return redirect()->route('booking.start');
        }

        $added = $this->addablePackage((int) $package);

        return $added !== null
            ? $this->addedPackageForm($added, null, BookingFlowState::value('extras', [])[$added->id] ?? null)
            : redirect()->route('booking.review');
    }

    public function storeAddedPackage(StepOfferRequest $request, string $locale, string $package): RedirectResponse
    {
        if (! $this->readyForReview()) {
            return redirect()->route('booking.start');
        }

        $added = $this->addablePackage((int) $package);

        if ($added !== null) {
            $extras = BookingFlowState::value('extras', []);
            $extras[$added->id] = $request->offerData();

            BookingFlowState::put(['extras' => $extras, 'hold_key' => null]);
        }

        return redirect()->route('booking.review');
    }

    public function removeAddedPackage(string $locale, string $package): RedirectResponse
    {
        BookingFlowState::put(['extras' => $this->extrasWithout((int) $package), 'hold_key' => null]);

        return redirect()->route('booking.review');
    }

    /** @param array<string, mixed>|null $entered  what they entered before; otherwise their first day */
    private function addedPackageForm(Package $package, ?PackageOffer $offer, ?array $entered): View
    {
        return view('booking::front.steps.offer', [
            'offer' => $offer,
            'savingOnAdded' => $offer === null ? $this->savingOffer($package) : null,
            'offered' => $package,
            'values' => $entered ?? ['start_date' => BookingFlowState::value('start_date')],
            'asksLicence' => StepOfferRequest::needsLicenceQuestions($package),
            'transferAreas' => $package->isTransfer() ? TransferArea::query()->active()->ordered()->get() : collect(),
            'hourlyStartTimes' => $package->isHourly() ? HourlySchedule::startTimes($package) : [],
            'hourlyLengths' => $package->isHourly() ? HourlySchedule::lengths($package) : [],
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        if (! $this->readyForReview()) {
            return redirect()->route('booking.start');
        }

        $request->validate([
            'terms_accepted' => ['accepted'],
            // A tuk tuk can't be driven until the Sri Lankan permit is arranged from the licence.
            'licence_documents_accepted' => $this->checkoutNeedsLicence() ? ['accepted'] : ['nullable'],
        ], [
            'licence_documents_accepted.accepted' => __('core::front.licence_accept_required'),
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

        // A saving came or went since the review page: show the new total first.
        if ($this->extraSavings() !== BookingFlowState::value('extra_savings', [])) {
            return redirect()->route('booking.review')
                ->withErrors(['booking' => __('core::front.booking_total_changed')]);
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

        $customerData = [
            'email' => $state['email'],
            'full_name' => trim($state['first_name'].' '.$state['last_name']),
            'phone' => $state['phone'] ?? null,
            'nationality' => $state['nationality'] ?? null,
            'passport_number' => $state['passport_number'] ?? null,
            'locale_preference' => app()->getLocale(),
            'pickup_type' => $state['pickup_type'],
            'delivery_zone_id' => $state['delivery_zone_id'] ?? null,
            'transfer_direction' => $isTransfer ? $state['transfer_direction'] : null,
            'flight_number' => $isTransfer ? $state['flight_number'] : null,
            'transfer_address' => $isTransfer ? $state['transfer_address'] : null,
            'transfer_area_id' => $isTransfer && ! empty($state['transfer_area_id']) ? (int) $state['transfer_area_id'] : null,
            'has_international_permit' => (bool) ($state['has_international_permit'] ?? false),
            'special_requests' => $state['special_requests'] ?? null,
        ];
        $source = ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()];
        $holdData = [
            'hold_key' => $holdKey,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'vehicle_id' => BookingFlowState::isStay() ? ($state['vehicle_id'] ?? null) : null,
            'package_id' => $state['package_id'],
            'customer_session_id' => $request->session()->getId(),
        ];
        $chosenOffer = $this->chosenOffer();
        $others = $this->otherBookings($state, $chosenOffer, $holdKey, $customerData, $request->session()->getId());

        try {
            if ($others !== []) {
                $booking = $this->bookings->createBookingsTogether(
                    holdData: $holdData,
                    customerData: $customerData,
                    price: $price,
                    others: $others,
                    source: $source,
                );
            } else {
                $booking = $this->bookings->createManualBooking(
                    holdData: $holdData,
                    customerData: $customerData,
                    price: $price,
                    source: $source,
                );
            }
        } catch (NoVehicleAvailableException|UniqueConstraintViolationException $exception) {
            // A double-clicked "Confirm": the other request (same hold key)
            // already holds — or has already booked — the vehicle, so this
            // one lost the race to *itself*. confirmHold() is idempotent and
            // waits on that hold's row lock, so it returns the very same
            // booking instead of an error or a second booking.
            $ownHold = BookingHold::query()->where('hold_key', $holdKey)->first();

            // The first package was fine but an added one has nothing free:
            // its hold was never made and the rest were just released, so
            // take that one off and show the new total.
            if ($others !== [] && $ownHold?->status === BookingHold::STATUS_RELEASED) {
                $failed = collect($others)->first(fn (array $other) => ! BookingHold::query()->where('hold_key', $other['hold']['hold_key'])->exists());
                $failedPackageId = (int) ($failed['hold']['package_id'] ?? 0);

                BookingFlowState::put([
                    'offer' => (int) $chosenOffer?->offered_package_id === $failedPackageId ? null : $state['offer'],
                    'extras' => $this->extrasWithout($failedPackageId),
                    'hold_key' => null,
                ]);

                return redirect()->route('booking.review')
                    ->withErrors(['booking' => __('core::front.package_added_unavailable')]);
            }

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
        $booking = $this->findBookingOrFail($reference, ['customer', 'vehicle.category', 'package', 'deliveryZone', 'transferArea']);
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

        if (! in_array($kind, BookingFlowState::KINDS, true)) {
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
     * The chosen line's vehicles come from partners (tuk tuks): every
     * booking is accepted and there is no availability to check. SUVs,
     * bikes, airport vehicles and stays are registered units instead.
     */
    private function lineHasUnlimitedUnits(): bool
    {
        return $this->bookableLines()->firstWhere('id', BookingFlowState::lineId())?->unlimited_units ?? false;
    }

    /**
     * No line chosen yet (straight to /booking/start, or an old session):
     * the first line of the flow's kind with something to book — Tuk Tuk for
     * rentals — so the package step never mixes tuk tuk and Vehicle Rent
     * packages.
     */
    private function defaultLine(): void
    {
        if (BookingFlowState::lineId() !== null) {
            return;
        }

        $lines = $this->bookableLines()->where('kind', BookingFlowState::kind());
        $lineId = ($lines->first(fn (ProductCategory $line) => $line->packages()->where('is_active', true)->exists()) ?? $lines->first())?->id;

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
            ->whereIn('kind', BookingFlowState::KINDS)
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

    /** An offer the package being booked has right now. */
    private function availableOffer(int $offerId): ?PackageOffer
    {
        return Package::query()->find(BookingFlowState::value('package_id'))?->availableOffers()->firstWhere('id', $offerId);
    }

    /** The offer the customer added on the review step, if it still stands. */
    private function chosenOffer(): ?PackageOffer
    {
        $chosen = BookingFlowState::value('offer');

        return is_array($chosen) ? $this->availableOffer((int) ($chosen['offer_id'] ?? 0)) : null;
    }

    /**
     * Whether any package in this checkout (the main one, one taken through
     * an offer, or one added at review) needs the customer to send their
     * driving licence after booking.
     */
    private function checkoutNeedsLicence(): bool
    {
        $packageIds = array_filter([
            BookingFlowState::value('package_id'),
            $this->chosenOffer()?->offered_package_id,
            ...array_keys(BookingFlowState::value('extras') ?? []),
        ]);

        return Package::query()->whereKey($packageIds)->where('requires_licence_documents', true)->exists();
    }

    /**
     * What can be added to the package being booked: anything else on sale,
     * surfing and other activities too, though on their own those are booked
     * by message.
     */
    private function addablePackages(): Builder
    {
        return Package::query()
            ->active()
            ->currentlyValid()
            ->inVisibleCategory()
            ->whereKeyNot((int) BookingFlowState::value('package_id'));
    }

    /** One package to add, unless it goes through an offer on the package being booked. */
    private function addablePackage(int $packageId): ?Package
    {
        return in_array($packageId, $this->packagesAddedThroughOffers(), true) ? null : $this->addablePackages()->find($packageId);
    }

    /**
     * The offer's card adds these, so the saving on the package being booked
     * is never missed. Only one offer is taken, so with one taken, the
     * packages of its other offers can be added like any other.
     *
     * @return int[]
     */
    private function packagesAddedThroughOffers(): array
    {
        $chosen = $this->chosenOffer();

        if ($chosen !== null) {
            return [$chosen->offered_package_id];
        }

        return Package::query()->find(BookingFlowState::value('package_id'))?->availableOffers()->pluck('offered_package_id')->all() ?? [];
    }

    /**
     * The best offer on an added package that another package in this
     * checkout earns: booked in either order, the customer gets the saving.
     */
    private function savingOffer(Package $added): ?PackageOffer
    {
        $inCheckout = [
            (int) BookingFlowState::value('package_id'),
            ...array_keys(BookingFlowState::value('extras', [])),
            ...array_filter([$this->chosenOffer()?->offered_package_id]),
        ];

        return PackageOffer::query()
            ->where('package_id', $added->id)
            ->whereIn('offered_package_id', array_diff($inCheckout, [$added->id]))
            ->orderByDesc('discount')
            ->first();
    }

    /**
     * @param array<string, mixed> $data
     * @return array{price: PriceBreakdown, start_at: string, end_at: string, customer: array<string, mixed>}
     */
    private function addedPlan(Package $added, array $data): array
    {
        $plan = $this->offers->plan($added, $data);
        $offer = $this->savingOffer($added);

        return $offer !== null ? [...$plan, 'price' => $this->pricing->withOffer($added, $plan['price'], $offer)] : $plan;
    }

    /**
     * The savings on added packages right now.
     *
     * @return array<int, int>  added package id => offer id
     */
    private function extraSavings(): array
    {
        $extras = BookingFlowState::value('extras', []);

        return $this->addablePackages()->whereKey(array_keys($extras))->get()
            ->mapWithKeys(fn (Package $added) => [$added->id => $this->savingOffer($added)?->id])
            ->filter()
            ->sortKeys()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function extrasWithout(int $packageId): array
    {
        return array_diff_key(BookingFlowState::value('extras', []), [$packageId => true]);
    }

    /**
     * What to book along with the first package: the offer's first, as that
     * is the booking its saving depends on, then each package added.
     *
     * @param array<string, mixed> $state
     * @param array<string, mixed> $customerData
     * @return list<array{hold: array<string, mixed>, customer: array<string, mixed>, price: PriceBreakdown}>
     */
    private function otherBookings(array $state, ?PackageOffer $offer, string $holdKey, array $customerData, string $sessionId): array
    {
        $other = function (array $plan, Package $package, string $keySuffix) use ($holdKey, $customerData, $sessionId): array {
            return [
                'hold' => [
                    // From the first key, so a double click finds it again.
                    'hold_key' => $holdKey.$keySuffix,
                    'start_at' => $plan['start_at'],
                    'end_at' => $plan['end_at'],
                    'package_id' => $package->id,
                    'customer_session_id' => $sessionId,
                ],
                'customer' => [...$customerData, ...$plan['customer']],
                'price' => $plan['price'],
            ];
        };

        $others = $offer !== null ? [$other($this->offers->plan($offer->offeredPackage, $state['offer']), $offer->offeredPackage, '-offer')] : [];
        $extras = $state['extras'] ?? [];

        foreach ($this->addablePackages()->whereKey(array_keys($extras))->orderBy('id')->get() as $package) {
            $others[] = $other($this->addedPlan($package, $extras[$package->id]), $package, '-'.$package->id);
        }

        return $others;
    }

    private function notEligibleMessage(): string
    {
        return __(BookingFlowState::isStay() ? 'core::front.booking_package_not_eligible_stay' : 'core::front.booking_package_not_eligible');
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
            // The chosen area (it is optional) may have been switched off, or
            // the pickup time come too close, since step 1.
            $problem = ! empty(BookingFlowState::value('transfer_area_id')) && $this->transferAreaFromState() === null
                ? __('core::front.booking_dates_no_longer_valid')
                : TransferSchedule::problem((string) BookingFlowState::value('start_date'), BookingFlowState::value('transfer_time'));

            if ($problem !== null) {
                BookingFlowState::put(['hold_key' => null]);

                return redirect()->route('booking.start')->withErrors(['transfer_time' => $problem]);
            }
        }

        $package = $this->flowPackages()->find(BookingFlowState::value('package_id'));

        if ($package === null || $days < $package->min_days || ($package->max_days !== null && $days > $package->max_days) || ! $package->hasPriceForDays($days)) {
            BookingFlowState::put(['package_id' => null, 'hold_key' => null]);

            return redirect()->route('booking.package')
                ->withErrors(['package_id' => $this->notEligibleMessage()]);
        }

        // Priced only by the destination area, and none was chosen.
        if ($package->needsTransferArea() && $this->transferAreaFromState() === null) {
            BookingFlowState::put(['hold_key' => null]);

            return redirect()->route('booking.start')->withErrors(['transfer_area_id' => __('core::front.transfer_area_needed')]);
        }

        // Its group prices may have changed since the package was chosen.
        if ($package->isGroupPriced() && ! $package->hasPriceForPeople((int) BookingFlowState::value('people'))) {
            BookingFlowState::put(['package_id' => null, 'hold_key' => null]);

            return redirect()->route('booking.package')->withErrors(['package_id' => __('core::front.booking_people_not_available')]);
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

        // Taken off the sale, or its dates have passed: the customer must see
        // the total without the saving before confirming.
        $chosen = BookingFlowState::value('offer');

        if (is_array($chosen)) {
            $offer = $this->chosenOffer();

            if ($offer === null
                || CarbonImmutable::parse($chosen['start_date'])->lt(CarbonImmutable::today())
                || OfferedBookingPlanner::problems($offer->offeredPackage, $chosen) !== []) {
                BookingFlowState::put(['offer' => null, 'hold_key' => null]);

                return redirect()->route('booking.review')
                    ->withErrors(['booking' => __('core::front.offer_no_longer_valid')]);
            }
        }

        // The same for each package added.
        $extras = BookingFlowState::value('extras', []);

        if ($extras !== []) {
            $added = $this->addablePackages()->whereKey(array_keys($extras))->get()->keyBy('id');
            $stillValid = array_filter($extras, fn (array $data, int $id) => $added->has($id)
                && ! CarbonImmutable::parse($data['start_date'])->lt(CarbonImmutable::today())
                && OfferedBookingPlanner::problems($added->get($id), $data) === [], ARRAY_FILTER_USE_BOTH);

            if (count($stillValid) !== count($extras)) {
                BookingFlowState::put(['extras' => $stillValid, 'hold_key' => null]);

                return redirect()->route('booking.review')
                    ->withErrors(['booking' => __('core::front.package_added_unavailable')]);
            }
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
        if (BookingFlowState::isTransfer() && is_string(BookingFlowState::value('transfer_time'))) {
            return TransferSchedule::range((string) BookingFlowState::value('start_date'), BookingFlowState::value('transfer_time'), $this->transferAreaFromState());
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

        $deliveryZone = ($state['pickup_type'] ?? null) === 'delivery' && ! empty($state['delivery_zone_id'])
            ? DeliveryZone::query()->find($state['delivery_zone_id'])
            : null;

        $price = $this->pricing->calculate(
            $package,
            $start,
            $days,
            $addonSelections,
            $deliveryZone,
            hours: $package->isHourly() ? (int) ($state['hours'] ?? 0) : null,
            transferArea: $this->transferAreaFromState(),
            people: $package->isGroupPriced() ? (int) ($state['people'] ?? 0) : null,
            drivers: $package->requires_licence_documents ? (int) ($state['drivers'] ?? 1) : null,
        );
        $offer = $this->chosenOffer();

        return $offer !== null ? $this->pricing->withOffer($package, $price, $offer) : $price;
    }
}
