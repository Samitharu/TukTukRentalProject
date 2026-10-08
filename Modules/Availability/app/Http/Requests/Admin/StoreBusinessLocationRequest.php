<?php

declare(strict_types=1);

namespace Modules\Availability\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Availability\Models\BusinessLocation;
use Modules\Core\Rules\GoogleMapsUrl;
use Modules\Localization\Support\TranslatableRules;

final class StoreBusinessLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BusinessLocation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            ...TranslatableRules::forField('name', max: 120),
            'address' => ['nullable', 'string', 'max:500'],
            'google_maps_url' => ['nullable', 'string', 'max:500', new GoogleMapsUrl()],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'is_pickup_point' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
