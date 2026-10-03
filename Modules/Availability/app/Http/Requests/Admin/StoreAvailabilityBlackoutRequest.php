<?php

declare(strict_types=1);

namespace Modules\Availability\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Availability\Models\AvailabilityBlackout;

final class StoreAvailabilityBlackoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AvailabilityBlackout::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
