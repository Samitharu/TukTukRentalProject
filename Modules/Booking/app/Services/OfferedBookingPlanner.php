<?php

declare(strict_types=1);

namespace Modules\Booking\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Modules\Availability\Models\TransferArea;
use Modules\Booking\Models\Booking;
use Modules\Booking\Support\HourlySchedule;
use Modules\Booking\Support\TransferSchedule;
use Modules\Package\Models\Package;
use Modules\Pricing\DataObjects\PriceBreakdown;
use Modules\Pricing\Services\PricingService;

/**
 * The second booking of a package offer, for the public review step and the
 * staff manual booking form alike. The customer is already known, so only
 * what this package needs is asked: its dates, or for an airport pickup the
 * trip. Form fields are posted under `offer[...]`.
 *
 * normalize() gives the shape every other method after validation works on:
 * end_date is the last day booked, as in BookingFlowState.
 */
final class OfferedBookingPlanner
{
    public function __construct(private readonly PricingService $pricing)
    {
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(Package $package, bool $byStaff = false): array
    {
        $startDate = ['required', 'date', 'after_or_equal:today'];

        if (! $byStaff) {
            $startDate[] = 'before_or_equal:'.now()->addDays((int) config('availability.maximum_advance_days'))->toDateString();
        }

        return ['offer.start_date' => $startDate, ...match (true) {
            $package->isTransfer() => [
                'offer.transfer_direction' => ['required', Rule::in(Booking::TRANSFER_DIRECTIONS)],
                'offer.transfer_time' => ['required', 'date_format:H:i'],
                'offer.flight_number' => ['required', 'string', 'max:10', 'regex:'.TransferSchedule::FLIGHT_NUMBER_PATTERN],
                'offer.transfer_area_id' => ['required', 'integer', Rule::exists('transfer_areas', 'id')->where('is_active', true)],
                'offer.transfer_address' => ['required', 'string', 'max:255'],
            ],
            $package->isHourly() => [
                'offer.start_time' => ['required', 'string'],
                'offer.hours' => ['required', 'integer'],
            ],
            $package->isActivity() => $package->pricing_model === Package::MODEL_PER_PERSON ? ['offer.people' => ['required', 'integer', 'min:1', 'max:50']] : [],
            $package->isStay() => ['offer.end_date' => ['required', 'date', 'after:offer.start_date']],
            default => ['offer.end_date' => ['required', 'date', 'after_or_equal:offer.start_date']],
        }];
    }

    /**
     * @param  array<string, mixed>  $input  the validated `offer` fields
     * @return array<string, mixed>
     */
    public static function normalize(Package $package, array $input): array
    {
        $start = (string) $input['start_date'];

        return [...$input, 'end_date' => match (true) {
            $package->isTransfer(), $package->isHourly(), $package->isActivity() => $start,
            $package->isStay() => CarbonImmutable::parse($input['end_date'])->subDay()->toDateString(),
            default => (string) $input['end_date'],
        }];
    }

    /**
     * What validation rules can't say, on normalized fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>  form field => translated message
     */
    public static function problems(Package $package, array $data, bool $byStaff = false): array
    {
        $start = (string) $data['start_date'];

        if ($package->isTransfer()) {
            if (! TransferArea::query()->active()->whereKey($data['transfer_area_id'] ?? 0)->exists()) {
                return ['offer.transfer_area_id' => __('core::front.booking_dates_no_longer_valid')];
            }

            $problem = $byStaff ? null : TransferSchedule::problem($start, $data['transfer_time'] ?? null);

            return $problem === null ? [] : ['offer.transfer_time' => $problem];
        }

        if ($package->isHourly()) {
            $problem = HourlySchedule::problem($package, $start, $data['start_time'] ?? null, $data['hours'] ?? null);

            return $problem === null ? [] : ['offer.start_time' => $problem];
        }

        if ($package->isActivity()) {
            $people = (int) ($data['people'] ?? 0);

            // Its tiers count people: with none covering them there is no price.
            return $package->pricing_model === Package::MODEL_PER_PERSON && ! $package->pricingTiers()->where('min_days', '<=', $people)->where(fn ($tier) => $tier->whereNull('max_days')->orWhere('max_days', '>=', $people))->exists()
                ? ['offer.people' => __('core::front.booking_people_not_available')]
                : [];
        }

        $days = self::days($data);

        if ($days < $package->min_days || ($package->max_days !== null && $days > $package->max_days) || ! $package->hasPriceForDays($days)) {
            return ['offer.end_date' => __($package->isStay() ? 'core::front.booking_package_not_eligible_stay' : 'core::front.booking_package_not_eligible')];
        }

        $maxDays = (int) config('booking.max_rental_days');

        if (! $byStaff && $days > $maxDays) {
            return ['offer.end_date' => __($package->isStay() ? 'core::front.booking_stay_too_long' : 'core::front.booking_rental_too_long', ['days' => $maxDays])];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data  normalized fields
     * @return array{price: PriceBreakdown, start_at: string, end_at: string, customer: array<string, mixed>}
     */
    public function plan(Package $package, array $data): array
    {
        $area = $package->isTransfer() ? TransferArea::query()->findOrFail($data['transfer_area_id']) : null;
        $hours = $package->isHourly() ? (int) $data['hours'] : null;
        $start = (string) $data['start_date'];

        [$startAt, $endAt] = match (true) {
            $area !== null => array_map(fn (CarbonImmutable $at) => $at->toDateTimeString(), TransferSchedule::range($start, $data['transfer_time'], $area)),
            $hours !== null => array_map(fn (CarbonImmutable $at) => $at->toDateTimeString(), HourlySchedule::range($start, $data['start_time'], $hours)),
            default => [$start, (string) $data['end_date']],
        };

        return [
            'price' => $this->pricing->calculate($package, CarbonImmutable::parse($start), self::days($data), hours: $hours, transferArea: $area, people: isset($data['people']) ? (int) $data['people'] : null),
            'start_at' => $startAt,
            'end_at' => $endAt,
            'customer' => [
                'pickup_type' => 'office',
                'delivery_zone_id' => null,
                'transfer_direction' => $area !== null ? $data['transfer_direction'] : null,
                'flight_number' => $area !== null ? $data['flight_number'] : null,
                'transfer_address' => $area !== null ? $data['transfer_address'] : null,
                'transfer_area_id' => $area?->id,
                // Only a rental's driver needs a permit.
                'has_international_permit' => ! $package->isStay() && ! $package->isTransfer() && ! $package->isActivity() && (bool) ($data['has_international_permit'] ?? false),
                'special_requests' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function days(array $data): int
    {
        return (int) CarbonImmutable::parse($data['start_date'])->diffInDays(CarbonImmutable::parse($data['end_date'])) + 1;
    }

    /**
     * Form field names for validation messages.
     *
     * @return array<string, string>
     */
    public static function attributes(Package $package): array
    {
        return [
            'offer.start_date' => __(match (true) {
                $package->isTransfer() => 'core::front.transfer_date',
                $package->isStay() => 'core::front.booking_check_in',
                $package->isActivity() => 'core::front.booking_activity_date',
                default => 'core::front.booking_start_date',
            }),
            'offer.end_date' => __($package->isStay() ? 'core::front.booking_check_out' : 'core::front.booking_end_date'),
            'offer.start_time' => __('core::front.booking_start_time'),
            'offer.hours' => __('core::front.booking_hours'),
            'offer.people' => __('core::front.booking_people'),
            'offer.transfer_direction' => __('core::front.transfer_direction'),
            'offer.transfer_time' => __('core::front.transfer_time'),
            'offer.flight_number' => __('core::front.transfer_flight_number'),
            'offer.transfer_area_id' => __('core::front.transfer_area'),
            'offer.transfer_address' => __('core::front.transfer_address'),
            'offer.has_valid_licence' => __('core::front.booking_has_valid_licence'),
            'offer.has_international_permit' => __('core::front.booking_has_international_permit'),
        ];
    }
}
