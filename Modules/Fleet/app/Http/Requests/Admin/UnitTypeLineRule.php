<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Requests\Admin;

use Illuminate\Validation\Validator;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Package\Models\ProductCategory;

/**
 * Shared by Store/UpdateVehicleCategoryRequest: a unit type's product line
 * must be of the same kind — scooters can go under "Vehicle Rent", never
 * under "Stays" or "Airport Pickup".
 */
final class UnitTypeLineRule
{
    public static function check(Validator $validator, mixed $kind, mixed $productCategoryId): void
    {
        if ($validator->errors()->hasAny(['kind', 'product_category_id']) || ! is_numeric($productCategoryId)) {
            return;
        }

        $lineKind = ProductCategory::query()->whereKey((int) $productCategoryId)->value('kind');

        if ($lineKind !== ($kind ?: VehicleCategory::KIND_VEHICLE)) {
            $validator->errors()->add('product_category_id', __('Pick a product line of the same type (rental, stay or airport pickup).'));
        }
    }
}
