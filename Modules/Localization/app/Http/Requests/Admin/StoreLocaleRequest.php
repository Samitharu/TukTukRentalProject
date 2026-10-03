<?php

declare(strict_types=1);

namespace Modules\Localization\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \Modules\Localization\Models\Locale::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:2', 'alpha', Rule::unique('locales', 'code')],
            'name' => ['required', 'string', 'max:60'],
            'native_name' => ['required', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'flag_icon' => ['nullable', 'string', 'max:10'],
        ];
    }
}
