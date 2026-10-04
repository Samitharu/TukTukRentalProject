<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Localization\Support\TranslatableRules;

final class StoreVehicleCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', VehicleCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [
            ...TranslatableRules::forField('name', max: 120),
            ...TranslatableRules::forField('description', max: 2000, requireDefault: false),
            'kind' => ['nullable', Rule::in(VehicleCategory::KINDS)],
            'icon' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
