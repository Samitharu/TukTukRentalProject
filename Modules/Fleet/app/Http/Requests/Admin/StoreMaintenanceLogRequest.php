<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreMaintenanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('vehicle')) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }
}
