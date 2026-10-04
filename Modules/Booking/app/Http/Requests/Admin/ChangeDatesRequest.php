<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ChangeDatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('booking')) ?? false;
    }

    public function rules(): array
    {
        // For a stay, end_date is the check-out day: at least one night.
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', $this->route('booking')?->isStay() ? 'after:start_date' : 'after_or_equal:start_date'],
        ];
    }

    /**
     * The new range as BookingService stores it: a stay's check-out day
     * becomes its last night (see Booking::isStay()).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        $start = CarbonImmutable::parse($this->string('start_date')->toString());
        $end = CarbonImmutable::parse($this->string('end_date')->toString());

        return [$start, $this->route('booking')->isStay() ? $end->subDay() : $end];
    }
}
