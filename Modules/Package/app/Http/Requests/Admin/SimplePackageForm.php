<?php

declare(strict_types=1);

namespace Modules\Package\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Package\Models\Package;
use Modules\Package\Support\PackageCharge;

/**
 * The simple admin package form, shared by Store/UpdatePackageRequest: it
 * posts "how is it charged" (`charge`), one `price` and one vehicle type
 * instead of pricing models, tiers and unit-type checkboxes. prepare()
 * turns those into the package's real fields before the usual validation
 * runs; requests without `charge` (older clients, tests) are unchanged.
 */
final class SimplePackageForm
{
    public static function prepare(FormRequest $request, ?Package $existing = null): void
    {
        if ($request->filled('charge') && in_array($request->input('charge'), PackageCharge::ALL, true)) {
            $request->merge(PackageCharge::attributes((string) $request->input('charge'), $request->all()));
        }

        // One "vehicle type" select instead of the unit-type checkboxes.
        if ($request->has('vehicle_type_id')) {
            $request->merge(['category_ids' => $request->filled('vehicle_type_id') ? [(int) $request->input('vehicle_type_id')] : []]);
        }

        if (! $request->filled('sort_order')) {
            $request->merge(['sort_order' => $existing?->sort_order ?? 0]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'charge' => ['nullable', Rule::in(PackageCharge::ALL)],
            'price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'price_eur' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'package_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'daily_km' => ['nullable', 'integer', 'min:0'],
            'daily_extra_km_rate' => ['nullable', 'numeric', 'min:0'],
            'daily_extra_km_rate_eur' => ['nullable', 'numeric', 'min:0'],
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_categories,id'],
            'offered_addon_ids' => ['nullable', 'array'],
            'offered_addon_ids.*' => ['integer', 'exists:addons,id'],
            'included_addon_ids' => ['nullable', 'array'],
            'included_addon_ids.*' => ['integer', 'exists:addons,id'],
            // package id => what the customer saves by adding that package.
            'offers' => ['nullable', 'array'],
            'offers.*.discount' => ['nullable', 'required_with:offers.*.discount_eur', 'numeric', 'min:0.01', 'max:1000000'],
            'offers.*.discount_eur' => ['nullable', 'required_with:offers.*.discount', 'numeric', 'min:0.01', 'max:1000000'],
        ];
    }

    public static function validate(Validator $validator, FormRequest $request, string $kind, ?Package $existing = null): void
    {
        $charge = $request->input('charge');

        if (! is_string($charge) || $charge === '' || $validator->errors()->has('charge')) {
            return;
        }

        if (! PackageCharge::allows($kind, $charge, $existing)) {
            $validator->errors()->add('charge', __('That way of charging is not available for this category.'));

            return;
        }

        // A package priced by rental length (several tiers) keeps those
        // prices; the single price box only manages a one-price package.
        $usesLengthPrices = $existing !== null && $existing->pricingTiers()->count() > 1;

        if (! $usesLengthPrices && ! $request->filled('price')) {
            $validator->errors()->add('price', __('Enter the price.'));
        }

        if (! $usesLengthPrices && ! $request->filled('price_eur')) {
            $validator->errors()->add('price_eur', __('Enter the price in EUR.'));
        }

        if ($charge === PackageCharge::KM_PACKAGE) {
            if (! $request->filled('included_km')) {
                $validator->errors()->add('included_km', __('Enter how many km the package includes.'));
            }

            if (! $request->filled('extra_km_rate')) {
                $validator->errors()->add('extra_km_rate', __('Enter the price for each extra km.'));
            }

            if (! $request->filled('extra_km_rate_eur')) {
                $validator->errors()->add('extra_km_rate_eur', __('Enter the price for each extra km in EUR.'));
            }
        }

        if ($charge === PackageCharge::PER_DAY && $request->filled('daily_extra_km_rate') && ! $request->filled('daily_km')) {
            $validator->errors()->add('daily_km', __('Set the km limit per day, so extra km can be charged beyond it.'));
        }

        if ($charge === PackageCharge::PER_DAY && $request->filled('daily_extra_km_rate') && ! $request->filled('daily_extra_km_rate_eur')) {
            $validator->errors()->add('daily_extra_km_rate_eur', __('Enter the price for each extra km in EUR.'));
        }
    }
}
