<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Front;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StepDatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pickup_type' => ['required', Rule::in(['office', 'delivery'])],
            'business_location_id' => ['required_if:pickup_type,office', 'nullable', 'integer', 'exists:business_locations,id'],
            'delivery_zone_id' => ['required_if:pickup_type,delivery', 'nullable', 'integer', 'exists:delivery_zones,id'],
        ];
    }
}
