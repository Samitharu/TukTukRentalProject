<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class RecordOdometerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('booking')) ?? false;
    }

    public function rules(): array
    {
        return [
            'odometer_start' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'odometer_end' => ['nullable', 'integer', 'min:0', 'max:9999999', 'gte:odometer_start'],
        ];
    }

    public function messages(): array
    {
        return [
            'odometer_end.gte' => __('The return reading can\'t be lower than the pickup reading.'),
        ];
    }
}
