<?php

declare(strict_types=1);

namespace Modules\Package\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Localization\Support\TranslatableRules;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;

/**
 * Create and update share one request: the only difference is that a
 * category's booking style is locked once it has packages, since their
 * pricing models and bookings were set up for that style.
 */
final class ProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->category();

        return $category === null
            ? ($this->user()?->can('create', ProductCategory::class) ?? false)
            : ($this->user()?->can('update', $category) ?? false);
    }

    public function rules(): array
    {
        return [
            ...TranslatableRules::forField('name', max: 120),
            ...TranslatableRules::forField('description', max: 2000, requireDefault: false),
            'kind' => ['required', Rule::in(Package::KINDS)],
            'image' => ['nullable', 'image', 'max:8192', 'mimes:jpg,jpeg,png,webp'],
            'remove_image' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $category = $this->category();

                if ($category !== null
                    && $this->input('kind') !== $category->kind
                    && $category->packages()->withTrashed()->exists()) {
                    $validator->errors()->add('kind', __('This category already has packages, so its booking style can\'t change. Create a new category instead.'));
                }
            },
        ];
    }

    private function category(): ?ProductCategory
    {
        $category = $this->route('category');

        return $category instanceof ProductCategory ? $category : null;
    }
}
