<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class CancelRelatedBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('booking')) ?? false;
    }

    public function rules(): array
    {
        return [
            'booking_ids' => ['required', 'array', 'min:1'],
            'booking_ids.*' => ['integer'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'booking_ids.required' => __('Tick at least one booking to cancel.'),
        ];
    }
}
