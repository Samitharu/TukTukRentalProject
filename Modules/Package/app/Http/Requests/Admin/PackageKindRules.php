<?php

declare(strict_types=1);

namespace Modules\Package\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;

/**
 * Shared by StorePackageRequest and UpdatePackageRequest: a package books
 * one kind of unit, so any category/unit it is restricted to must be of
 * that kind — a "Surf & Stay" package restricted to a tuk tuk would be
 * booked by the night on a vehicle.
 */
final class PackageKindRules
{
    /**
     * @return array<int, mixed>
     */
    public static function kindRule(bool $required): array
    {
        return [$required ? 'nullable' : 'sometimes', Rule::in(VehicleCategory::KINDS)];
    }

    public static function validateRestrictions(Validator $validator, string $kind, array $categoryIds, array $vehicleIds): void
    {
        if ($validator->errors()->hasAny(['kind', 'category_ids.*', 'vehicle_ids.*'])) {
            return;
        }

        $wrongCategories = $categoryIds !== []
            && VehicleCategory::query()->whereIn('id', $categoryIds)->where('kind', '!=', $kind)->exists();

        if ($wrongCategories) {
            $validator->errors()->add('category_ids', __('Only categories of the package\'s type can be selected.'));
        }

        $wrongUnits = $vehicleIds !== []
            && Vehicle::query()
                ->whereIn('id', $vehicleIds)
                ->whereNotIn('category_id', VehicleCategory::query()->select('id')->where('kind', $kind))
                ->exists();

        if ($wrongUnits) {
            $validator->errors()->add('vehicle_ids', __('Only units of the package\'s type can be selected.'));
        }
    }
}
