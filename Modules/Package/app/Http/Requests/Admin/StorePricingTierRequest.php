<?php

declare(strict_types=1);

namespace Modules\Package\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StorePricingTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('package')) ?? false;
    }

    public function rules(): array
    {
        return [
            'min_days' => ['required', 'integer', 'min:1'],
            'max_days' => ['nullable', 'integer', 'gte:min_days'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
