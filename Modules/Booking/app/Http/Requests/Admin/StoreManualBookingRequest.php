<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Booking\Models\Booking;
use Modules\Booking\Support\HourlySchedule;
use Modules\Fleet\Models\Vehicle;
use Modules\Package\Models\Package;

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
            'start_time' => ['nullable', 'string'],
            'hours' => ['nullable', 'integer'],
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

    /**
     * For a stay package the end date is the check-out day, so it must be
     * at least one night after check-in; and a specific unit, if chosen,
     * must be of the package's kind (no cabana on a tuk tuk package).
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['package_id', 'vehicle_id', 'start_date', 'end_date'])) {
                    return;
                }

                $package = Package::query()->find($this->integer('package_id'));

                if ($package?->isActivity()) {
                    $validator->errors()->add('package_id', __('Activity packages are booked by message for now, not as a rental.'));

                    return;
                }

                if ($package?->isHourly()) {
                    if ($this->input('end_date') !== $this->input('start_date')) {
                        $validator->errors()->add('end_date', __('An hourly rental starts and ends on the same day.'));
                    }

                    $problem = HourlySchedule::problem($package, (string) $this->input('start_date'), $this->input('start_time'), $this->input('hours'));

                    if ($problem !== null) {
                        $validator->errors()->add('start_time', $problem);
                    }
                }

                if ($package?->isStay() && ! CarbonImmutable::parse($this->input('end_date'))->gt(CarbonImmutable::parse($this->input('start_date')))) {
                    $validator->errors()->add('end_date', __('Check-out must be at least one night after check-in.'));
                }

                $vehicle = $this->filled('vehicle_id') ? Vehicle::query()->with('category')->find($this->integer('vehicle_id')) : null;

                if ($package !== null && $vehicle !== null && $vehicle->kind() !== $package->kind) {
                    $validator->errors()->add('vehicle_id', $package->isStay()
                        ? __('This is a stay package — pick a cabana or room, not a tuk tuk.')
                        : __('This is a tuk tuk package — pick a tuk tuk, not a cabana or room.'));
                }
            },
        ];
    }
}
