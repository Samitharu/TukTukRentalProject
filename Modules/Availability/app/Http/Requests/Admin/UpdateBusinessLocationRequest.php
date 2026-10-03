<?php

declare(strict_types=1);

namespace Modules\Availability\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Localization\Support\TranslatableRules;

final class UpdateBusinessLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('location')) ?? false;
    }

    public function rules(): array
    {
        return [
            ...TranslatableRules::forField('name', max: 120),
            'address' => ['nullable', 'string', 'max:500'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'is_pickup_point' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
