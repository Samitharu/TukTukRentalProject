<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Booking\Models\Booking;
use Modules\Booking\Support\HourlySchedule;
use Modules\Core\Support\Countries;
use Modules\Fleet\Models\Vehicle;
use Modules\Package\Models\Package;

final class StoreManualBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Same normalization as the public details step: the select's
        // values are upper-case ISO codes, but accept "de" too.
        if ($this->filled('nationality')) {
            $this->merge(['nationality' => mb_strtoupper((string) $this->input('nationality'))]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'string'],
            'hours' => ['nullable', 'integer'],
            'pickup_type' => ['required', Rule::in(['office', 'delivery'])],
            'business_location_id' => ['nullable', 'integer', 'exists:business_locations,id'],
            'delivery_zone_id' => ['nullable', 'integer', 'exists:delivery_zones,id'],
            'addons' => ['nullable', 'array'],
            'addons.*' => ['integer', 'min:0', 'max:50'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:addons,id'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ];

        // The live price preview posts the same form before the customer
        // part is filled in — it only needs what affects price/availability.
        if ($this->isQuote()) {
            return $rules;
        }

        return [
            ...$rules,
            // An existing customer picked on the form: their stored email
            // is used, so name/email can be left as prefilled.
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'full_name' => ['required_without:customer_id', 'nullable', 'string', 'max:150'],
            'email' => ['required_without:customer_id', 'nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'nationality' => ['nullable', 'string', 'size:2', Rule::in(array_keys(Countries::all()))],
            'passport_number' => ['nullable', 'string', 'max:40'],
            'has_international_permit' => ['nullable', 'boolean'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'notify_customer' => ['nullable', 'boolean'],
        ];
    }

    public function isQuote(): bool
    {
        return $this->routeIs('admin.bookings.quote');
    }

    /**
     * addon id => quantity, from the form's quantity inputs plus the older
     * `addon_ids[]` checkbox format (quantity 1).
     *
     * @return array<int, int>
     */
    public function addonSelections(): array
    {
        $selections = [];

        foreach ($this->validated('addons') ?? [] as $addonId => $quantity) {
            if ((int) $quantity > 0) {
                $selections[(int) $addonId] = (int) $quantity;
            }
        }

        foreach ($this->validated('addon_ids') ?? [] as $addonId) {
            $selections[(int) $addonId] ??= 1;
        }

        return $selections;
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
