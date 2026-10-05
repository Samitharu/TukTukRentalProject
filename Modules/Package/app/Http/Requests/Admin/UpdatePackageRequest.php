<?php

declare(strict_types=1);

namespace Modules\Package\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
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
            ...PackageKindRules::rules(creating: false),
            'min_days' => ['nullable', 'required_unless:pricing_model,per_hour,per_person', 'integer', 'min:1'],
            'max_days' => ['nullable', 'integer', 'gte:min_days'],
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

    public function after(): array
    {
        return [
            fn (Validator $validator) => PackageKindRules::validate(
                $validator,
                PackageKindRules::kindFor($this->input('product_category_id'), $this->route('package')),
                $this->input('pricing_model'),
                $this->input('included_km'),
                $this->input('extra_km_rate'),
                (array) $this->input('category_ids', []),
                (array) $this->input('vehicle_ids', []),
            ),
        ];
    }
}
