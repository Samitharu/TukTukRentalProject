<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Localization\Support\TranslatableRules;

final class UpdateVehicleCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('category')) ?? false;
    }

    public function rules(): array
    {
        return [
            ...TranslatableRules::forField('name', max: 120),
            ...TranslatableRules::forField('description', max: 2000, requireDefault: false),
            'icon' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
