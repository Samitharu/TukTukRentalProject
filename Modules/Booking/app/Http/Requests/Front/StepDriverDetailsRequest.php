<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Front;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Booking\Support\BookingFlowState;
use Modules\Core\Support\Countries;

final class StepDriverDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Customer::$nationality is a char(2) ISO 3166-1 alpha-2 column —
        // normalize casing here so "lk" and "LK" both validate and store
        // consistently regardless of how the <select> option was authored.
        if ($this->filled('nationality')) {
            $this->merge(['nationality' => mb_strtoupper((string) $this->input('nationality'))]);
        }
    }

    public function rules(): array
    {
        // Nobody drives a cabana: stays skip the licence/permit questions
        // (the view doesn't render them either).
        if (BookingFlowState::isStay()) {
            return [
                ...$this->commonRules(),
                'has_valid_licence' => ['exclude'],
                'has_international_permit' => ['exclude'],
            ];
        }

        return [
            ...$this->commonRules(),
            // A hidden input mirrors each checkbox's name with value "0"
            // in the view, so these are always present in the request —
            // "accepted"/"boolean" can rely on that rather than "nullable".
            'has_valid_licence' => ['accepted'],
            'has_international_permit' => ['required', 'boolean'],
        ];
    }

    private function commonRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:30'],
            'nationality' => ['required', 'string', 'size:2', Rule::in(array_keys(Countries::all()))],
            'passport_number' => ['required', 'string', 'max:40'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'marketing_opt_in' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'has_valid_licence' => __('core::front.booking_has_valid_licence'),
            'has_international_permit' => __('core::front.booking_has_international_permit'),
        ];
    }
}
