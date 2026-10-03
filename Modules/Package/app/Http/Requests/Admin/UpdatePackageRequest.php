<?php

declare(strict_types=1);

namespace Modules\Package\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Localization\Support\TranslatableRules;

final class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('package')) ?? false;
    }

    public function rules(): array
    {
        return [
            ...TranslatableRules::forField('name', max: 150),
            ...TranslatableRules::forField('description', max: 4000, requireDefault: false),
            'pricing_model' => ['required', Rule::in(['per_day', 'per_week', 'per_month', 'fixed_bundle', 'tiered'])],
            'min_days' => ['required', 'integer', 'min:1'],
            'max_days' => ['nullable', 'integer', 'gte:min_days'],
            'included_km' => ['nullable', 'integer', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_is_percent' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:vehicle_categories,id'],
            'vehicle_ids' => ['nullable', 'array'],
            'vehicle_ids.*' => ['integer', 'exists:vehicles,id'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:8192', 'mimes:jpg,jpeg,png,webp'],
        ];
    }
}
