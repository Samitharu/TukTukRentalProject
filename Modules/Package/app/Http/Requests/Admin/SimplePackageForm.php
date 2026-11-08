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
            'group_pricing' => ['sometimes', 'boolean'],
            // One row per group size: people from–to and the group's price.
            'group_prices' => ['nullable', 'array', 'max:20'],
            'group_prices.*.from' => ['nullable', 'integer', 'min:1', 'max:100'],
            'group_prices.*.to' => ['nullable', 'integer', 'min:1', 'max:100'],
            'group_prices.*.price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'group_prices.*.price_eur' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
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

        if ($request->boolean('group_pricing') && in_array($charge, PackageCharge::GROUP_PRICED, true)) {
            // The group rows replace the single price box.
            self::validateGroupPrices($validator, $request);
        } else {
            // A package priced by rental length (several tiers) keeps those
            // prices; the single price box only manages a one-price package.
            // A package leaving group pricing needs the single price again.
            $usesLengthPrices = $existing !== null && ! $existing->group_pricing && $existing->pricingTiers()->count() > 1;

            if (! $usesLengthPrices && ! $request->filled('price')) {
                $validator->errors()->add('price', __('Enter the price.'));
            }

            if (! $usesLengthPrices && ! $request->filled('price_eur')) {
                $validator->errors()->add('price_eur', __('Enter the price in EUR.'));
            }
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

    /**
     * The filled group-size rows, smallest group first. A row left
     * completely empty is ignored.
     *
     * @return array<int, array{from: int, to: int, price: float, price_eur: float}>
     */
    public static function groupPrices(FormRequest $request): array
    {
        return collect((array) $request->input('group_prices', []))
            ->filter(fn ($row) => is_array($row) && collect($row)->contains(fn ($value) => filled($value)))
            ->map(fn (array $row) => [
                'from' => (int) ($row['from'] ?? 0),
                'to' => (int) ($row['to'] ?? 0),
                'price' => (float) ($row['price'] ?? 0),
                'price_eur' => (float) ($row['price_eur'] ?? 0),
            ])
            ->sortBy('from')
            ->values()
            ->all();
    }

    private static function validateGroupPrices(Validator $validator, FormRequest $request): void
    {
        if ($validator->errors()->has('group_prices*')) {
            return;
        }

        foreach ((array) $request->input('group_prices', []) as $row) {
            if (! is_array($row) || ! collect($row)->contains(fn ($value) => filled($value))) {
                continue;
            }

            foreach (['from', 'to', 'price', 'price_eur'] as $field) {
                if (! filled($row[$field] ?? null)) {
                    $validator->errors()->add('group_prices', __('Fill in every box of a group size row: people from, people to, and both prices.'));

                    return;
                }
            }
        }

        $rows = self::groupPrices($request);

        if ($rows === []) {
            $validator->errors()->add('group_prices', __('Add at least one group size and its price.'));

            return;
        }

        foreach ($rows as $index => $row) {
            if ($row['to'] < $row['from']) {
                $validator->errors()->add('group_prices', __('In each row, "people to" must be at least "people from".'));

                return;
            }

            $next = $rows[$index + 1] ?? null;

            // 3–5 and 5–7 would give 5 people two prices.
            if ($next !== null && $next['from'] <= $row['to']) {
                $validator->errors()->add('group_prices', __('The group sizes :first and :second overlap. Each number of people can be in one row only, e.g. 3–5 and 6–7.', [
                    'first' => $row['from'].'–'.$row['to'],
                    'second' => $next['from'].'–'.$next['to'],
                ]));

                return;
            }
        }
    }
}
