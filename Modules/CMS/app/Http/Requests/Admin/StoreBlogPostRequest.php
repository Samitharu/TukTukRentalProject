<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Localization\Support\TranslatableRules;

final class StoreBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cms.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            ...TranslatableRules::forField('title', max: 200),
            ...TranslatableRules::forField('excerpt', max: 500, requireDefault: false),
            ...TranslatableRules::forField('body', type: 'string', max: 50000),
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
