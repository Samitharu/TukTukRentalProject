<?php

declare(strict_types=1);

namespace Modules\Package\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Localization\Support\TranslatableRules;
use Modules\Package\Models\Addon;

final class StoreAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Addon::class) ?? false;
    }

    public function rules(): array
    {
        return [
            ...TranslatableRules::forField('name', max: 120),
            ...TranslatableRules::forField('description', max: 1000, requireDefault: false),
            'price' => ['required', 'numeric', 'min:0'],
            'pricing_unit' => ['required', Rule::in(['flat', 'per_day'])],
            'max_quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
