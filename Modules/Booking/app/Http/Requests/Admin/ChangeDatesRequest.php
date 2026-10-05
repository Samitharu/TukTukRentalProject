<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Booking\Models\Booking;
use Modules\Booking\Support\HourlySchedule;
use Modules\Package\Models\Package;

final class ChangeDatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('booking')) ?? false;
    }

    public function rules(): array
    {
        // An hourly rental moves to a new day and start time, keeping its
        // length (its price was for that many hours).
        if ($this->booking()->isHourly()) {
            return [
                'start_date' => ['required', 'date'],
                'start_time' => ['required', 'string'],
            ];
        }

        // For a stay, end_date is the check-out day: at least one night.
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', $this->booking()->isStay() ? 'after:start_date' : 'after_or_equal:start_date'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->booking()->isHourly() || $validator->errors()->isNotEmpty()) {
                    return;
                }

                // Any length up to the opening window: the booking's own
                // hours are fixed, whatever the package allows today.
                $problem = HourlySchedule::problem(new Package(['min_hours' => 1]), $this->string('start_date')->toString(), $this->input('start_time'), $this->booking()->hours());

                if ($problem !== null) {
                    $validator->errors()->add('start_time', $problem);
                }
            },
        ];
    }

    /**
     * The new range as BookingService stores it: a stay's check-out day
     * becomes its last night (see Booking::isStay()); an hourly rental
     * gets its real start and return times.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        $start = CarbonImmutable::parse($this->string('start_date')->toString());

        if ($this->booking()->isHourly()) {
            return HourlySchedule::range($start->toDateString(), $this->string('start_time')->toString(), (int) $this->booking()->hours());
        }

        $end = CarbonImmutable::parse($this->string('end_date')->toString());

        return [$start, $this->booking()->isStay() ? $end->subDay() : $end];
    }

    private function booking(): Booking
    {
        return $this->route('booking');
    }
}
