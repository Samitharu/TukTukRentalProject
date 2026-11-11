<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Front;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Booking\Services\OfferedBookingPlanner;
use Modules\Booking\Support\BookingFlowState;
use Modules\Booking\Support\TransferSchedule;
use Modules\Package\Models\Package;
use Modules\Package\Models\PackageOffer;

/**
 * The review step's "add this package", with an offer's saving or without.
 * Everything about the customer is already known; a rental's licence
 * questions are only asked here if the first booking didn't ask them (a
 * stay or airport pickup).
 */
final class StepOfferRequest extends FormRequest
{
    private ?Package $offeredPackage = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->offered()?->isTransfer()) {
            $this->merge(['offer' => [
                ...(array) $this->input('offer', []),
                'flight_number' => TransferSchedule::normalizeFlightNumber($this->input('offer.flight_number')),
            ]]);
        }
    }

    /** Null when it doesn't exist: the controller redirects first, so only for safety. */
    private function offered(): ?Package
    {
        return $this->offeredPackage ??= $this->route('offer') !== null
            ? PackageOffer::query()->find((int) $this->route('offer'))?->offeredPackage
            : Package::query()->find((int) $this->route('package'));
    }

    public function asksLicence(): bool
    {
        return self::needsLicenceQuestions($this->offered());
    }

    public static function needsLicenceQuestions(?Package $offered): bool
    {
        return $offered !== null && ! $offered->isStay() && ! $offered->isTransfer() && ! $offered->isActivity() && ! $offered->isTour()
            && (BookingFlowState::isStay() || BookingFlowState::isTransfer() || BookingFlowState::isTour());
    }

    public function rules(): array
    {
        $offered = $this->offered();

        if ($offered === null) {
            return [];
        }

        return [
            ...OfferedBookingPlanner::rules($offered),
            ...($this->asksLicence() ? [
                'offer.has_valid_licence' => ['accepted'],
                'offer.has_international_permit' => ['required', 'boolean'],
            ] : []),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $offered = $this->offered();

                if ($offered === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach (OfferedBookingPlanner::problems($offered, $this->offerData()) as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'offer.start_date.after_or_equal' => __('core::front.booking_start_in_past'),
            'offer.start_date.before_or_equal' => __('core::front.booking_start_too_far', ['days' => (int) config('availability.maximum_advance_days')]),
            'offer.end_date.after_or_equal' => __('core::front.booking_end_before_start'),
            'offer.end_date.after' => __('core::front.booking_check_out_after_check_in'),
            'offer.flight_number.regex' => __('core::front.transfer_flight_invalid'),
            'offer.transfer_time.date_format' => __('core::front.transfer_time_invalid'),
        ];
    }

    public function attributes(): array
    {
        $offered = $this->offered();

        return $offered !== null ? OfferedBookingPlanner::attributes($offered) : [];
    }

    /**
     * The fields as they are kept in the flow state.
     *
     * @return array<string, mixed>
     */
    public function offerData(): array
    {
        $fields = (array) $this->input('offer', []);
        $known = array_map(fn (string $rule) => substr($rule, strlen('offer.')), array_keys($this->rules()));
        $data = OfferedBookingPlanner::normalize($this->offered(), array_intersect_key($fields, array_flip($known)));

        unset($data['has_valid_licence']);

        // A rental after a rental: the driver already answered this.
        if (! $this->asksLicence()) {
            $data['has_international_permit'] = (bool) BookingFlowState::value('has_international_permit', false);
        }

        return $data;
    }
}
