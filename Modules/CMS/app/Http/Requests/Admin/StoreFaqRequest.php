<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Localization\Support\TranslatableRules;

final class StoreFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cms.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            ...TranslatableRules::forField('question', max: 255),
            ...TranslatableRules::forField('answer', type: 'string', max: 5000),
            'category' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
