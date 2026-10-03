<?php

declare(strict_types=1);

namespace Modules\Package\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('package')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'price_modifier_type' => ['required', Rule::in(['fixed', 'percent'])],
            'price_modifier_value' => ['required', 'numeric'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'priority' => ['nullable', 'integer'],
        ];
    }
}
