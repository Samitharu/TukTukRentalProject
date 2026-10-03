<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Localization\Support\TranslatableRules;

final class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cms.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            ...TranslatableRules::forField('title', max: 150),
            ...TranslatableRules::forField('content', type: 'string', max: 50000, requireDefault: false),
            'template' => ['required', 'string', 'max:60'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
