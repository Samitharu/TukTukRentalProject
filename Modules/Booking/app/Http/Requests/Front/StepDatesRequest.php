<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Front;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Booking\Models\Booking;
use Modules\Booking\Support\BookingFlowState;
use Modules\Booking\Support\TransferSchedule;

/**
 * Step 1. For a rental: pickup and return dates (both days rented); it is
 * always collected at the office. For a stay: check-in and check-out — no
 * pickup at all, and the check-out morning is not a night stayed. For an
 * airport pickup: direction, date and time, flight, destination area and
 * address — a single trip on one day.
 */
final class StepDatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (BookingFlowState::isTransfer()) {
            $this->merge([
                'end_date' => $this->input('start_date'),
                'flight_number' => TransferSchedule::normalizeFlightNumber($this->input('flight_number')),
            ]);
        }
    }

    public function rules(): array
    {
        $latestStart = now()->addDays((int) config('availability.maximum_advance_days'))->toDateString();
        $startDate = ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.$latestStart];

        // Pickup is never asked: rentals are collected at the office (see
        // flowData()), so any posted pickup fields are ignored.
        $ignored = [
            'pickup_type' => ['exclude'],
            'delivery_zone_id' => ['exclude'],
        ];

        if (BookingFlowState::isTransfer()) {
            return [
                ...$ignored,
                'start_date' => $startDate,
                'end_date' => ['required', 'date'],
                'transfer_direction' => ['required', Rule::in(Booking::TRANSFER_DIRECTIONS)],
                'transfer_time' => ['required', 'date_format:H:i'],
                'flight_number' => ['required', 'string', 'max:10', 'regex:'.TransferSchedule::FLIGHT_NUMBER_PATTERN],
                'transfer_area_id' => ['required', 'integer', Rule::exists('transfer_areas', 'id')->where('is_active', true)],
                'transfer_address' => ['required', 'string', 'max:255'],
            ];
        }

        return [
            ...$ignored,
            'start_date' => $startDate,
            'end_date' => BookingFlowState::isStay()
                ? ['required', 'date', 'after:start_date']
                : ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * Rental length cap (config booking.max_rental_days) — a business limit
     * that also bounds how many per-day reservation rows one request can
     * make the database write. For a stay it caps nights. An airport
     * pickup must instead be far enough ahead (minimum notice).
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['start_date', 'end_date', 'transfer_time'])) {
                    return;
                }

                if (BookingFlowState::isTransfer()) {
                    $problem = TransferSchedule::problem((string) $this->input('start_date'), $this->input('transfer_time'));

                    if ($problem !== null) {
                        $validator->errors()->add('transfer_time', $problem);
                    }

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
            'flight_number.regex' => __('core::front.transfer_flight_invalid'),
            'transfer_time.date_format' => __('core::front.transfer_time_invalid'),
        ];
    }

    public function attributes(): array
    {
        return [
            'transfer_direction' => __('core::front.transfer_direction'),
            'transfer_time' => __('core::front.transfer_time'),
            'flight_number' => __('core::front.transfer_flight_number'),
            'transfer_area_id' => __('core::front.transfer_area'),
            'transfer_address' => __('core::front.transfer_address'),
        ];
    }

    /**
     * The validated input in the flow's internal shape. A rental is always
     * collected at the office; a stay's check-out becomes its last night,
     * and it has no pickup (see BookingFlowState::isStay()); an airport
     * pickup is a one-day booking whose times come from transfer_time.
     *
     * @return array<string, mixed>
     */
    public function flowData(): array
    {
        $data = $this->validated();
        $noPickup = ['pickup_type' => 'office', 'delivery_zone_id' => null];

        if (BookingFlowState::isTransfer()) {
            return [...$data, ...$noPickup, 'end_date' => $data['start_date']];
        }

        if (! BookingFlowState::isStay()) {
            return [...$data, ...$noPickup];
        }

        return [
            ...$data,
            ...$noPickup,
            'end_date' => CarbonImmutable::parse($data['end_date'])->subDay()->toDateString(),
        ];
    }
}
