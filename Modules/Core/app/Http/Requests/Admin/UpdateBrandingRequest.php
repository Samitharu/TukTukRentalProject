<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            // No SVG: Intervention's GD driver can't rasterize vector images,
            // and an uploaded SVG can carry an embedded <script>/event
            // handler — a real XSS vector this project otherwise guards
            // against carefully (brief §8).
            'logo' => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
            'hero_image' => ['nullable', 'image', 'max:8192', 'mimes:jpg,jpeg,png,webp'],
        ];
    }
}
