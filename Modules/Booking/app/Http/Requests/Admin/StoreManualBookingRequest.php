<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Booking\Models\Booking;

final class StoreManualBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pickup_type' => ['required', Rule::in(['office', 'delivery'])],
            'business_location_id' => ['nullable', 'integer', 'exists:business_locations,id'],
            'delivery_zone_id' => ['nullable', 'integer', 'exists:delivery_zones,id'],
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'nationality' => ['nullable', 'string', 'size:2'],
            'has_international_permit' => ['nullable', 'boolean'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:addons,id'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ];
    }
}
