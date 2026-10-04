<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Front;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Booking\Support\BookingFlowState;

/**
 * Step 1. For a tuk tuk: pickup and return dates (both days rented) plus
 * how the vehicle is collected. For a stay: check-in and check-out — no
 * pickup at all, and the check-out morning is not a night stayed.
 */
final class StepDatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $latestStart = now()->addDays((int) config('availability.maximum_advance_days'))->toDateString();
        $startRules = ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.$latestStart];

        if (BookingFlowState::isStay()) {
            return [
                'start_date' => $startRules,
                'end_date' => ['required', 'date', 'after:start_date'],
                'pickup_type' => ['exclude'],
                'business_location_id' => ['exclude'],
                'delivery_zone_id' => ['exclude'],
            ];
        }

        return [
            'start_date' => $startRules,
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pickup_type' => ['required', Rule::in(['office', 'delivery'])],
            'business_location_id' => ['required_if:pickup_type,office', 'nullable', 'integer', 'exists:business_locations,id'],
            'delivery_zone_id' => ['required_if:pickup_type,delivery', 'nullable', 'integer', 'exists:delivery_zones,id'],
        ];
    }

    /**
     * Rental length cap (config booking.max_rental_days) — a business limit
     * that also bounds how many per-day reservation rows one request can
     * make the database write. For a stay it caps nights.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                    return;
                }

                $isStay = BookingFlowState::isStay();
                $days = (int) CarbonImmutable::parse($this->input('start_date'))
                    ->diffInDays(CarbonImmutable::parse($this->input('end_date'))) + ($isStay ? 0 : 1);
                $maxDays = (int) config('booking.max_rental_days');

                if ($days > $maxDays) {
                    $validator->errors()->add('end_date', __($isStay ? 'core::front.booking_stay_too_long' : 'core::front.booking_rental_too_long', ['days' => $maxDays]));
                }
            },
        ];
    }

    public function messages(): array
    {
        $isStay = BookingFlowState::isStay();

        return [
            'start_date.after_or_equal' => __($isStay ? 'core::front.booking_check_in_in_past' : 'core::front.booking_start_in_past'),
            'start_date.before_or_equal' => __('core::front.booking_start_too_far', ['days' => (int) config('availability.maximum_advance_days')]),
            'end_date.after_or_equal' => __('core::front.booking_end_before_start'),
            'end_date.after' => __('core::front.booking_check_out_after_check_in'),
        ];
    }

    /**
     * The validated input in the flow's internal shape: a stay's check-out
     * becomes its last night, and it has no pickup (see
     * BookingFlowState::isStay()).
     *
     * @return array<string, mixed>
     */
    public function flowData(): array
    {
        $data = $this->validated();

        if (! BookingFlowState::isStay()) {
            return $data;
        }

        return [
            ...$data,
            'end_date' => CarbonImmutable::parse($data['end_date'])->subDay()->toDateString(),
            'pickup_type' => 'office',
            'business_location_id' => null,
            'delivery_zone_id' => null,
        ];
    }
}
