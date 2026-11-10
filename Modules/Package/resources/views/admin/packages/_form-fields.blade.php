@php
    use Modules\Package\Models\Package;
    use Modules\Package\Support\PackageCharge;

    $package = $package ?? null;
    $selectedCategoryId = (string) old('product_category_id', $package?->product_category_id ?? $preselectedCategoryId ?? $productCategories->first()?->id);
    $selectedCategory = $productCategories->firstWhere('id', (int) $selectedCategoryId);
    $selectedKind = $selectedCategory?->kind ?? Package::KIND_VEHICLE;
    // Tuk tuks come from partners: no vehicle type or units to pick.
    $selectedHasUnits = ! ($selectedCategory?->unlimited_units ?? false);
    $selectedCharge = old('charge', $charge ?? PackageCharge::BY_KIND[$selectedKind][0]);

    // The answers offered per booking style; an older package keeps its own.
    $charges = collect(PackageCharge::BY_KIND)
        ->flatMap(fn (array $answers, string $kind) => array_map(fn ($answer) => [$answer, $kind], $answers))
        ->groupBy(0)
        ->map(fn ($rows) => $rows->pluck(1)->implode(' '));
    if (in_array($charge ?? null, [PackageCharge::PER_WEEK, PackageCharge::PER_MONTH], true)) {
        $charges->put($charge, Package::KIND_VEHICLE);
    }

    $vehicleTypes = $categories->filter(fn ($type) => ! $type->isStay());
    $selectedVehicleType = (string) old('vehicle_type_id', collect($selectedCategoryIds ?? [])->first());
    $hasError = fn (string ...$fields) => collect($fields)->contains(fn ($field) => $errors->has($field));
    // After a validation error, the boxes as they were ticked (none ticked sends nothing).
    $tickedOffered = session()->hasOldInput() ? array_map('intval', old('offered_addon_ids', [])) : ($offeredAddonIds ?? []);
    $tickedIncluded = session()->hasOldInput() ? array_map('intval', old('included_addon_ids', [])) : ($includedAddonIds ?? []);

    // Prices by group size: the saved rows, or as typed before an error; always a few empty rows to add more.
    $groupPriced = (bool) old('group_pricing', $package?->group_pricing ?? false);
    $groupRows = session()->hasOldInput()
        ? array_values((array) old('group_prices', []))
        : ($package?->group_pricing ? $package->pricingTiers->map(fn ($tier) => ['from' => $tier->min_days, 'to' => $tier->max_days, 'price' => $tier->price, 'price_eur' => $tier->price_eur])->all() : []);
    $groupRows = array_pad($groupRows, max(count($groupRows) + 2, 4), ['from' => null, 'to' => null, 'price' => null, 'price_eur' => null]);
@endphp

<div data-package-form>
<x-admin::translatable-field name="name" :label="__('Package name')" :value="$package?->getTranslations('name') ?? []" :required="true" />

<div class="admin-form-field">
    <label for="product_category_id">{{ __('Category') }}</label>
    <select id="product_category_id" name="product_category_id" required data-package-category>
        @foreach ($productCategories as $productCategory)
            <option value="{{ $productCategory->id }}" data-kind="{{ $productCategory->kind }}" data-unlimited="{{ $productCategory->unlimited_units ? '1' : '0' }}" @selected($selectedCategoryId === (string) $productCategory->id)>
                {{ $productCategory->name }}{{ $productCategory->is_active ? '' : ' ('.__('hidden').')' }}
            </option>
        @endforeach
    </select>
    <p class="admin-hint" data-package-kind="activity" @if ($selectedKind !== 'activity') hidden @endif>
        {{ __('Shown on the website with its price and a "Book on WhatsApp" button.') }}
    </p>
</div>

<div class="admin-form-field" data-package-kind="vehicle transfer" data-needs-units @if (! $selectedHasUnits || ! in_array($selectedKind, ['vehicle', 'transfer'], true)) hidden @endif>
    <label for="vehicle_type_id">{{ __('Vehicle type') }}</label>
    <select id="vehicle_type_id" name="vehicle_type_id" data-vehicle-type>
        <option value="">{{ __('Any vehicle in this category') }}</option>
        @foreach ($vehicleTypes as $type)
            <option value="{{ $type->id }}" data-line="{{ $type->product_category_id }}" @selected($selectedVehicleType === (string) $type->id)>{{ $type->name }}</option>
        @endforeach
    </select>
    @error('category_ids')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
</div>

<fieldset class="admin-form-field">
    <legend style="font-weight:600;margin-bottom:0.5rem;">{{ __('How is it charged?') }}</legend>
    @foreach ($charges as $value => $kinds)
        <label style="display:block;font-weight:400;" data-charge-option data-kinds="{{ $kinds }}">
            <input type="radio" name="charge" value="{{ $value }}" @checked($selectedCharge === $value)> {{ PackageCharge::label($value) }}
        </label>
    @endforeach
    @error('charge')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
</fieldset>

<fieldset class="admin-form-field admin-location" data-charge="{{ implode(' ', PackageCharge::GROUP_PRICED) }}" @if (! in_array($selectedCharge, PackageCharge::GROUP_PRICED, true)) hidden @endif>
    <legend>{{ __('Price by group size') }}</legend>
    <label style="font-weight:400;"><input type="hidden" name="group_pricing" value="0"><input type="checkbox" name="group_pricing" value="1" data-group-pricing @checked($groupPriced)> {{ __('The price depends on how many people come (e.g. per boat)') }}</label>
    <div data-group-on @if (! $groupPriced) hidden @endif>
        <p class="admin-hint">{{ __('One row per group size. Example: 3 to 5 people 10, 6 to 7 people 18. For a package charged per hour or per day, the price is for each hour or day, so 4 people for 2 hours costs 2 × 10. Customers choose how many people when they book; group sizes without a row cannot be booked.') }}</p>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('People from') }}</th>
                    <th>{{ __('People to') }}</th>
                    <th>{{ __('Price for the group') }} (USD)</th>
                    <th>{{ __('Price for the group') }} (EUR)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groupRows as $index => $row)
                    <tr>
                        <td><input type="number" name="group_prices[{{ $index }}][from]" value="{{ $row['from'] ?? '' }}" min="1" max="100" aria-label="{{ __('People from') }}" style="width:5rem;"></td>
                        <td><input type="number" name="group_prices[{{ $index }}][to]" value="{{ $row['to'] ?? '' }}" min="1" max="100" aria-label="{{ __('People to') }}" style="width:5rem;"></td>
                        <td><input type="number" name="group_prices[{{ $index }}][price]" value="{{ $row['price'] ?? '' }}" min="0" step="0.01" aria-label="{{ __('Price for the group') }} (USD)" style="width:7rem;"></td>
                        <td><input type="number" name="group_prices[{{ $index }}][price_eur]" value="{{ $row['price_eur'] ?? '' }}" min="0" step="0.01" aria-label="{{ __('Price for the group') }} (EUR)" style="width:7rem;"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="admin-hint">{{ __('Need more rows? Save, and two more empty rows appear.') }}</p>
    </div>
    @error('group_prices')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
</fieldset>

@if ($usesLengthPrices ?? false)
    <p class="admin-alert" data-group-off>{{ __('This package has different prices depending on the rental length — change them under "Prices by rental length" below.') }}</p>
@else
    <div class="admin-location__coords" data-group-off @if ($groupPriced && in_array($selectedCharge, PackageCharge::GROUP_PRICED, true)) hidden @endif>
        <div class="admin-form-field">
            @foreach ($charges as $value => $kinds)
                <label for="price" data-charge="{{ $value }}" @if ($selectedCharge !== $value) hidden @endif>{{ PackageCharge::priceLabel($value) }} (USD)</label>
            @endforeach
            <input type="number" id="price" name="price" value="{{ old('price', $price ?? '') }}" min="0" step="0.01" required>
            @error('price')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
        </div>
        <div class="admin-form-field">
            @foreach ($charges as $value => $kinds)
                <label for="price_eur" data-charge="{{ $value }}" @if ($selectedCharge !== $value) hidden @endif>{{ PackageCharge::priceLabel($value) }} (EUR)</label>
            @endforeach
            <input type="number" id="price_eur" name="price_eur" value="{{ old('price_eur', $priceEur ?? '') }}" min="0" step="0.01" required>
            @error('price_eur')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
        </div>
    </div>
@endif

<fieldset class="admin-form-field admin-location" data-charge="per_hour" @if ($selectedCharge !== 'per_hour') hidden @endif>
    <legend>{{ __('Hours') }}</legend>
    <div class="admin-location__coords">
        <div>
            <label for="min_hours">{{ __('Minimum hours') }}</label>
            <input type="number" id="min_hours" name="min_hours" value="{{ old('min_hours', $package?->min_hours ?? 1) }}" min="1" max="24">
        </div>
        <div>
            <label for="max_hours">{{ __('Maximum hours (blank = no limit)') }}</label>
            <input type="number" id="max_hours" name="max_hours" value="{{ old('max_hours', $package?->max_hours) }}" min="1" max="24">
        </div>
    </div>
    <p class="admin-hint">{{ __('Same-day rental, from :first:00 until :last:00.', ['first' => sprintf('%02d', config('booking.hourly.first_start_hour')), 'last' => sprintf('%02d', config('booking.hourly.last_return_hour'))]) }}</p>
</fieldset>

<fieldset class="admin-form-field admin-location" data-charge="km_package" @if ($selectedCharge !== 'km_package') hidden @endif>
    <legend>{{ __('What the price includes') }}</legend>
    <div class="admin-location__coords">
        <div>
            <label for="included_km">{{ __('Km included') }}</label>
            <input type="number" id="included_km" name="included_km" value="{{ old('included_km', $package?->included_km) }}" min="0">
            @error('included_km')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="extra_km_rate">{{ __('Price per extra km') }} (USD)</label>
            <input type="number" id="extra_km_rate" name="extra_km_rate" value="{{ old('extra_km_rate', $package?->extra_km_rate) }}" min="0" step="0.01">
            @error('extra_km_rate')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="extra_km_rate_eur">{{ __('Price per extra km') }} (EUR)</label>
            <input type="number" id="extra_km_rate_eur" name="extra_km_rate_eur" value="{{ old('extra_km_rate_eur', $package?->extra_km_rate_eur) }}" min="0" step="0.01">
            @error('extra_km_rate_eur')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="package_days">{{ __('Days') }}</label>
            <input type="number" id="package_days" name="package_days" value="{{ old('package_days', $package?->min_days ?? 1) }}" min="1" max="60">
        </div>
    </div>
    <p class="admin-hint">{{ __('Example: 30 for 1 day including 100 km, extra km 0.30 each. Staff enter the odometer at return and extra km is added to the booking automatically.') }}</p>
</fieldset>

<div class="admin-form-field">
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $package?->is_active ?? true))> {{ __('Show on the website') }}</label>
</div>

{{-- Ticked by default for a new rental; after an error, as it was left (an unticked box sends nothing). --}}
<div class="admin-form-field" data-package-kind="vehicle" @if ($selectedKind !== Package::KIND_VEHICLE) hidden @endif>
    <label><input type="checkbox" name="requires_licence_documents" value="1" @checked(session()->hasOldInput() ? (bool) old('requires_licence_documents') : ($package?->requires_licence_documents ?? true))> {{ __('Customer must send their driving licence after booking (for the Sri Lankan driving permit)') }}</label>
    <p class="admin-hint">{{ __('Customers must agree to send it before they can book, and are shown how to send it by WhatsApp or email. Untick it for a package where our driver drives, such as a guided tour.') }}</p>
    <div class="admin-location__coords">
        <div>
            <label for="licence_fee">{{ __('Permit fee (USD)') }}</label>
            <input type="number" id="licence_fee" name="licence_fee" value="{{ old('licence_fee', $package?->licence_fee) }}" min="0" step="0.01">
            @error('licence_fee')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="licence_fee_eur">{{ __('Permit fee (EUR)') }}</label>
            <input type="number" id="licence_fee_eur" name="licence_fee_eur" value="{{ old('licence_fee_eur', $package?->licence_fee_eur) }}" min="0" step="0.01">
            @error('licence_fee_eur')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
        </div>
    </div>
    <p class="admin-hint">{{ __('Added once to each booking of this package, shown on the package and as its own line on the bill. Leave empty for no fee.') }}</p>
</div>

<div class="admin-form-field">
    <label for="cover_image">{{ $package?->images->isNotEmpty() ? __('New cover image') : __('Cover image') }}</label>
    <input type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/webp">
    <p class="admin-hint">{{ __('The main photo customers see first. A wide photo works best. JPG, PNG or WebP, up to 8 MB.') }}</p>
    @error('cover_image')<p class="field-error" style="color:#b3261e;">{{ __('The photo must be a JPG, PNG or WebP image of up to 8 MB.') }}</p>@enderror
</div>

<div class="admin-form-field">
    <label for="images">{{ __('More photos') }}</label>
    <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
    <p class="admin-hint">{{ __('Related photos customers can browse. You can select several at once.') }}</p>
    @error('images.*')<p class="field-error" style="color:#b3261e;">{{ __('Each photo must be a JPG, PNG or WebP image of up to 8 MB.') }}</p>@enderror
</div>

<fieldset class="admin-form-field">
    <legend style="font-weight:600;margin-bottom:0.5rem;">{{ __('Add-ons') }}</legend>
    @if ($addons->isEmpty())
        <p class="admin-hint">{{ __('No add-ons yet.') }} <a href="{{ route('admin.addons.create') }}">{{ __('Add add-on') }}</a></p>
    @else
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Customer can add') }}</th>
                    <th>{{ __('Included in price') }}</th>
                    <th>{{ __('Add-on') }}</th>
                    <th>{{ __('Price') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($addons as $addon)
                    <tr>
                        <td><input type="checkbox" name="offered_addon_ids[]" value="{{ $addon->id }}" aria-label="{{ __('Customer can add') }}: {{ $addon->name }}" @checked(in_array($addon->id, $tickedOffered, true))></td>
                        <td><input type="checkbox" name="included_addon_ids[]" value="{{ $addon->id }}" aria-label="{{ __('Included in price') }}: {{ $addon->name }}" @checked(in_array($addon->id, $tickedIncluded, true))></td>
                        <td>{{ $addon->name }}{{ $addon->is_active ? '' : ' ('.__('hidden').')' }}</td>
                        <td>{{ $addon->displayPrice() }} {{ $addon->pricing_unit === 'per_day' ? __('per day') : __('one time') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    @error('offered_addon_ids.*')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
    @error('included_addon_ids.*')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
</fieldset>

<details class="admin-form-field" data-package-kind="vehicle stay transfer" @if ($selectedKind === 'activity') hidden @endif @if ($hasError('offers.*') || ($packageOffers ?? collect())->isNotEmpty()) open @endif>
    <summary style="cursor:pointer;font-weight:600;">{{ __('Special offers (optional)') }}</summary>
    <p class="admin-hint">{{ __('When a customer books this package and also adds one below, this package gets cheaper by the amount you enter. Example: airport pickup $15, saves $3 with a tuk tuk, so the customer pays $12. Leave a row empty for no offer.') }}</p>
    @if ($offerablePackages->isEmpty())
        <p class="admin-hint">{{ __('No other packages yet.') }}</p>
    @else
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Customer also books') }}</th>
                    <th>{{ __('Customer saves') }} (USD)</th>
                    <th>{{ __('Customer saves') }} (EUR)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($offerablePackages as $offerable)
                    @php $existingOffer = ($packageOffers ?? collect())->get($offerable->id); @endphp
                    <tr>
                        <td>{{ $offerable->name }}{{ $offerable->productCategory ? ' — '.$offerable->productCategory->name : '' }}{{ $offerable->is_active ? '' : ' ('.__('hidden').')' }}</td>
                        <td>
                            <input type="number" name="offers[{{ $offerable->id }}][discount]" value="{{ old('offers.'.$offerable->id.'.discount', $existingOffer?->discount) }}" min="0.01" step="0.01" style="max-width:7rem;" aria-label="{{ __('Customer saves') }} (USD): {{ $offerable->name }}">
                            @error('offers.'.$offerable->id.'.discount')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
                        </td>
                        <td>
                            <input type="number" name="offers[{{ $offerable->id }}][discount_eur]" value="{{ old('offers.'.$offerable->id.'.discount_eur', $existingOffer?->discount_eur) }}" min="0.01" step="0.01" style="max-width:7rem;" aria-label="{{ __('Customer saves') }} (EUR): {{ $offerable->name }}">
                            @error('offers.'.$offerable->id.'.discount_eur')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</details>

<details class="admin-form-field" @if ($hasError('description', 'min_days', 'max_days', 'daily_km', 'daily_extra_km_rate', 'daily_extra_km_rate_eur', 'deposit_amount', 'valid_from', 'valid_until', 'sort_order', 'vehicle_ids')) open @endif>
    <summary style="cursor:pointer;font-weight:600;">{{ __('More options (optional)') }}</summary>

    <div style="margin-top:1rem;">
        <x-admin::translatable-field name="description" :label="__('Description')" :value="$package?->getTranslations('description') ?? []" :textarea="true" />

        <div class="admin-location__coords" data-charge="per_day per_night per_week per_month" @if (! in_array($selectedCharge, ['per_day', 'per_night', 'per_week', 'per_month'], true)) hidden @endif>
            <div class="admin-form-field">
                <label for="min_days">{{ __('Minimum days / nights') }}</label>
                <input type="number" id="min_days" name="min_days" value="{{ old('min_days', $package?->min_days ?? 1) }}" min="1">
            </div>
            <div class="admin-form-field">
                <label for="max_days">{{ __('Maximum days / nights (blank = no limit)') }}</label>
                <input type="number" id="max_days" name="max_days" value="{{ old('max_days', $package?->max_days) }}" min="1">
            </div>
        </div>

        <div class="admin-location__coords" data-charge="per_day" @if ($selectedCharge !== 'per_day') hidden @endif>
            <div class="admin-form-field">
                <label for="daily_km">{{ __('Km limit per day (blank = unlimited)') }}</label>
                <input type="number" id="daily_km" name="daily_km" value="{{ old('daily_km', $selectedCharge === 'per_day' ? $package?->included_km : null) }}" min="0">
                @error('daily_km')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
            </div>
            <div class="admin-form-field">
                <label for="daily_extra_km_rate">{{ __('Price per extra km') }} (USD)</label>
                <input type="number" id="daily_extra_km_rate" name="daily_extra_km_rate" value="{{ old('daily_extra_km_rate', $selectedCharge === 'per_day' ? $package?->extra_km_rate : null) }}" min="0" step="0.01">
            </div>
            <div class="admin-form-field">
                <label for="daily_extra_km_rate_eur">{{ __('Price per extra km') }} (EUR)</label>
                <input type="number" id="daily_extra_km_rate_eur" name="daily_extra_km_rate_eur" value="{{ old('daily_extra_km_rate_eur', $selectedCharge === 'per_day' ? $package?->extra_km_rate_eur : null) }}" min="0" step="0.01">
                @error('daily_extra_km_rate_eur')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="admin-location__coords">
            <div class="admin-form-field">
                <label for="deposit_amount">{{ __('Deposit') }}</label>
                <input type="number" id="deposit_amount" name="deposit_amount" value="{{ old('deposit_amount', $package?->deposit_amount) }}" min="0" step="0.01">
                <label style="font-weight:400;"><input type="checkbox" name="deposit_is_percent" value="1" @checked(old('deposit_is_percent', $package?->deposit_is_percent))> {{ __('Deposit is a percentage') }}</label>
            </div>
            <div class="admin-form-field">
                <label for="sort_order">{{ __('Order on the website') }}</label>
                <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $package?->sort_order ?? 0) }}" min="0">
            </div>
        </div>

        <div class="admin-location__coords">
            <div class="admin-form-field">
                <label for="valid_from">{{ __('Sell from (blank = always)') }}</label>
                <input type="date" id="valid_from" name="valid_from" value="{{ old('valid_from', $package?->valid_from?->toDateString()) }}">
            </div>
            <div class="admin-form-field">
                <label for="valid_until">{{ __('Sell until (blank = always)') }}</label>
                <input type="date" id="valid_until" name="valid_until" value="{{ old('valid_until', $package?->valid_until?->toDateString()) }}">
            </div>
        </div>

        <div class="admin-form-field">
            <label><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $package?->is_featured))> {{ __('Featured on homepage') }}</label>
        </div>

        <fieldset class="admin-form-field" data-package-kind="vehicle stay transfer" data-needs-units @if (! $selectedHasUnits || $selectedKind === 'activity') hidden @endif>
            <legend style="font-weight:600;margin-bottom:0.5rem;">{{ __('Only these units (leave empty = any) — e.g. the cabanas a surf package is sold with') }}</legend>
            @foreach ($vehicles as $vehicle)
                <label style="display:block;font-weight:400;">
                    <input type="checkbox" name="vehicle_ids[]" value="{{ $vehicle->id }}" @checked(in_array($vehicle->id, old('vehicle_ids', $selectedVehicleIds ?? []), true))>
                    {{ $vehicle->adminLabel() }} <span class="admin-badge {{ $vehicle->isStay() ? 'admin-badge--stay' : '' }}">{{ $vehicle->category?->name }}</span>
                </label>
            @endforeach
            @error('vehicle_ids')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
        </fieldset>
    </div>
</details>
</div>

<script src="{{ asset_v('assets/js/admin-package-form.js') }}" defer nonce="{{ csp_nonce() }}"></script>
