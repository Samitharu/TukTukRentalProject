<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\Rules\GoogleMapsEmbedUrl;
use Modules\Core\Rules\TripadvisorUrl;

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
            'tripadvisor_url' => ['nullable', 'string', 'max:500', new TripadvisorUrl()],
            'footer_map_url' => ['nullable', 'string', 'max:1000', new GoogleMapsEmbedUrl()],
        ];
    }

    /** Google gives a whole <iframe> to copy: keep only its address. */
    protected function prepareForValidation(): void
    {
        $map = $this->input('footer_map_url');

        if (is_string($map) && preg_match('/<iframe\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $map, $match) === 1) {
            $this->merge(['footer_map_url' => html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5)]);
        }
    }
}
