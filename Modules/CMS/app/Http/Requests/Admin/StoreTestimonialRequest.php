<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Localization\Support\TranslatableRules;

final class StoreTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cms.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:150'],
            'country' => ['nullable', 'string', 'size:2'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            ...TranslatableRules::forField('content', type: 'string', max: 2000),
            'source' => ['nullable', 'string', 'max:60'],
            'is_approved' => ['sometimes', 'boolean'],
        ];
    }
}
