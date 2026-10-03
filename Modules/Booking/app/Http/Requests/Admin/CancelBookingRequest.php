<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('booking')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
