<?php

declare(strict_types=1);

namespace Modules\Localization\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('locale')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'native_name' => ['required', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'flag_icon' => ['nullable', 'string', 'max:10'],
        ];
    }
}
