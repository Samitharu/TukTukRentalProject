<?php

declare(strict_types=1);

namespace Modules\Package\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;

/**
 * Shared by StorePackageRequest and UpdatePackageRequest. A package's
 * category decides its booking style (kind), and with it which pricing
 * models fit and which units it may be restricted to — a "Surf & Stay"
 * package restricted to a tuk tuk would be booked by the night on a vehicle.
 */
final class PackageKindRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(bool $creating): array
    {
        return [
            'product_category_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:product_categories,id'],
            'pricing_model' => ['required', Rule::in(array_unique(array_merge(...array_values(Package::PRICING_MODELS_BY_KIND))))],
            'min_hours' => ['nullable', 'required_if:pricing_model,'.Package::MODEL_PER_HOUR, 'integer', 'min:1', 'max:24'],
            'max_hours' => ['nullable', 'integer', 'min:1', 'max:24', 'gte:min_hours'],
            'included_km' => ['nullable', 'integer', 'min:0'],
            'included_km_per_day' => ['sometimes', 'boolean'],
            'extra_km_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public static function kindFor(mixed $categoryId, ?Package $existing = null): string
    {
        $kind = $categoryId !== null ? ProductCategory::query()->whereKey($categoryId)->value('kind') : null;

        return $kind ?? $existing?->kind ?? Package::KIND_VEHICLE;
    }

    public static function validate(Validator $validator, string $kind, mixed $pricingModel, mixed $includedKm, mixed $extraKmRate, array $categoryIds, array $vehicleIds): void
    {
        if ($validator->errors()->hasAny(['product_category_id', 'pricing_model', 'category_ids.*', 'vehicle_ids.*'])) {
            return;
        }

        if (! in_array($pricingModel, Package::PRICING_MODELS_BY_KIND[$kind] ?? [], true)) {
            $validator->errors()->add('pricing_model', __('That pricing type isn\'t available for this category\'s booking style.'));
        }

        if (($extraKmRate !== null && $extraKmRate !== '') && ($includedKm === null || $includedKm === '')) {
            $validator->errors()->add('included_km', __('Set how many km are included, so extra km can be charged beyond it.'));
        }

        // Unit restrictions only mean something for units of the same kind;
        // an activity has no units at all, so every restriction is wrong.
        $wrongCategories = $categoryIds !== []
            && VehicleCategory::query()->whereIn('id', $categoryIds)->where('kind', '!=', $kind)->exists();

        if ($wrongCategories) {
            $validator->errors()->add('category_ids', __('Only unit types of the package\'s booking style can be selected.'));
        }

        $wrongUnits = $vehicleIds !== []
            && Vehicle::query()
                ->whereIn('id', $vehicleIds)
                ->whereNotIn('category_id', VehicleCategory::query()->select('id')->where('kind', $kind))
                ->exists();

        if ($wrongUnits) {
            $validator->errors()->add('vehicle_ids', __('Only units of the package\'s booking style can be selected.'));
        }
    }
}
