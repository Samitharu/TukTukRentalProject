<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Front;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StepDatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $latestStart = now()->addDays((int) config('availability.maximum_advance_days'))->toDateString();

        return [
            'start_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.$latestStart],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pickup_type' => ['required', Rule::in(['office', 'delivery'])],
            'business_location_id' => ['required_if:pickup_type,office', 'nullable', 'integer', 'exists:business_locations,id'],
            'delivery_zone_id' => ['required_if:pickup_type,delivery', 'nullable', 'integer', 'exists:delivery_zones,id'],
        ];
    }

    /**
     * Rental length cap (config booking.max_rental_days) — a business limit
     * that also bounds how many per-day reservation rows one request can
     * make the database write.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                    return;
                }

                $days = (int) CarbonImmutable::parse($this->input('start_date'))
                    ->diffInDays(CarbonImmutable::parse($this->input('end_date'))) + 1;
                $maxDays = (int) config('booking.max_rental_days');

                if ($days > $maxDays) {
                    $validator->errors()->add('end_date', __('core::front.booking_rental_too_long', ['days' => $maxDays]));
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.after_or_equal' => __('core::front.booking_start_in_past'),
            'start_date.before_or_equal' => __('core::front.booking_start_too_far', ['days' => (int) config('availability.maximum_advance_days')]),
            'end_date.after_or_equal' => __('core::front.booking_end_before_start'),
        ];
    }
}
