<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Modules\Core\Rules\GoogleMapsUrl;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Localization\Support\TranslatableRules;

/**
 * Validation shared by StoreVehicleRequest and UpdateVehicleRequest. Which
 * fields apply depends on the chosen category's kind: tuk tuks need a
 * plate, gearbox and fuel type; stays have none of those but must say
 * where they are (a Google Maps link and/or a dropped pin).
 */
final class UnitRules
{
    /** Checkbox options on the unit form, per kind. */
    public const array FEATURES = [
        VehicleCategory::KIND_VEHICLE => ['helmet_included', 'phone_holder', 'gps', 'bluetooth_speaker', 'storage_box', 'sun_canopy'],
        VehicleCategory::KIND_STAY => ['wifi', 'air_conditioning', 'hot_water', 'private_bathroom', 'sea_view', 'breakfast_included', 'kitchen', 'parking'],
    ];

    public static function isStayCategory(mixed $categoryId): bool
    {
        return is_numeric($categoryId)
            && VehicleCategory::query()->whereKey((int) $categoryId)->where('kind', VehicleCategory::KIND_STAY)->exists();
    }

    public static function rules(bool $isStay, ?int $ignoreVehicleId = null): array
    {
        $plateUnique = Rule::unique('vehicles', 'plate_no')->ignore($ignoreVehicleId);

        return [
            ...TranslatableRules::forField('name', max: 120),
            ...TranslatableRules::forField('description', max: 2000, requireDefault: false),
            'category_id' => ['required', 'integer', 'exists:vehicle_categories,id'],
            'plate_no' => $isStay ? ['exclude'] : ['required', 'string', 'max:20', $plateUnique],
            'model' => $isStay ? ['exclude'] : ['nullable', 'string', 'max:120'],
            'year' => $isStay ? ['exclude'] : ['nullable', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
            'colour' => $isStay ? ['exclude'] : ['nullable', 'string', 'max:60'],
            'seats' => ['required', 'integer', 'min:1', 'max:'.($isStay ? 30 : 6)],
            'transmission' => $isStay ? ['exclude'] : ['required', Rule::in(['manual', 'automatic'])],
            'fuel_type' => $isStay ? ['exclude'] : ['required', Rule::in(['petrol', 'diesel', 'electric'])],
            'features' => ['nullable', 'array'],
            // Checkboxes of the other kind may still be ticked when JS is off
            // (both sets render); the controller keeps only this kind's.
            'features.*' => ['string', Rule::in([...self::FEATURES[VehicleCategory::KIND_VEHICLE], ...self::FEATURES[VehicleCategory::KIND_STAY]])],
            'address' => ['nullable', 'string', 'max:500'],
            'google_maps_url' => [
                ...($isStay ? ['required_without:lat'] : []),
                'nullable', 'string', 'max:500', new GoogleMapsUrl(),
            ],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'status' => ['required', Rule::in(['active', 'maintenance', 'retired'])],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:8192', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public static function messages(): array
    {
        return [
            'google_maps_url.required_without' => __('A cabana or room needs its location: paste its Google Maps link, or drop the pin on the map.'),
        ];
    }
}
