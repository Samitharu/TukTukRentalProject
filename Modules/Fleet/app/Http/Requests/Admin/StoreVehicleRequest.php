<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Fleet\Models\Vehicle;
use Modules\Localization\Support\TranslatableRules;

final class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vehicle::class) ?? false;
    }

    public function rules(): array
    {
        return [
            ...TranslatableRules::forField('name', max: 120),
            ...TranslatableRules::forField('description', max: 2000, requireDefault: false),
            'category_id' => ['required', 'integer', 'exists:vehicle_categories,id'],
            'plate_no' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate_no')],
            'model' => ['nullable', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
            'colour' => ['nullable', 'string', 'max:60'],
            'seats' => ['required', 'integer', 'min:1', 'max:6'],
            'transmission' => ['required', Rule::in(['manual', 'automatic'])],
            'fuel_type' => ['required', Rule::in(['petrol', 'diesel', 'electric'])],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:60'],
            'status' => ['required', Rule::in(['active', 'maintenance', 'retired'])],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:8192', 'mimes:jpg,jpeg,png,webp'],
        ];
    }
}
