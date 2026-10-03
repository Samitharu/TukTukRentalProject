<?php

declare(strict_types=1);

namespace Modules\Pricing\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pricing.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:3', 'alpha', Rule::unique('currencies', 'code')],
            'symbol' => ['required', 'string', 'max:5'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
