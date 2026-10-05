<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Front;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Booking\Support\BookingFlowState;
use Modules\Booking\Support\HourlySchedule;
use Modules\Package\Models\Package;

/**
 * An hourly package card carries its own start-time and length pickers
 * (`hourly[<package id>][start_time|hours]`), so only the chosen
 * package's pair is read and checked.
 */
final class StepPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'hourly' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('package_id') || ($package = $this->package()) === null || ! $package->isHourly()) {
                    return;
                }

                [$startTime, $hours] = $this->hourlyChoice();
                $problem = HourlySchedule::problem($package, (string) BookingFlowState::value('start_date'), $startTime, $hours);

                if ($problem !== null) {
                    $validator->errors()->add('package_id', $problem);
                }
            },
        ];
    }

    public function package(): ?Package
    {
        return Package::query()->find($this->integer('package_id'));
    }

    /**
     * @return array{0: mixed, 1: mixed}  start time, hours — as submitted
     */
    public function hourlyChoice(): array
    {
        $choice = $this->input('hourly.'.$this->integer('package_id'), []);

        return [$choice['start_time'] ?? null, $choice['hours'] ?? null];
    }
}
