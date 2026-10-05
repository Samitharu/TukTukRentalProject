@php
    use Modules\Package\Models\Package;

    $package = $package ?? null;
    $selectedCategoryId = (string) old('product_category_id', $package?->product_category_id ?? $preselectedCategoryId ?? $productCategories->first()?->id);
    $selectedKind = $productCategories->firstWhere('id', (int) $selectedCategoryId)?->kind ?? Package::KIND_VEHICLE;
    $selectedModel = old('pricing_model', $package?->pricing_model ?? Package::MODEL_TIERED);

    $pricingModels = [
        Package::MODEL_TIERED => __('Per day / night — rate depends on length (tiers)'),
        Package::MODEL_PER_DAY => __('Per day / night'),
        Package::MODEL_PER_WEEK => __('Per week'),
        Package::MODEL_PER_MONTH => __('Per month'),
        Package::MODEL_PER_HOUR => __('Hourly — same-day rental, tuk tuk blocked for the day'),
        Package::MODEL_PER_PERSON => __('Per person'),
        Package::MODEL_FIXED_BUNDLE => __('Fixed price for the whole package'),
    ];
    $kindsFor = fn (string $model) => implode(' ', array_keys(array_filter(Package::PRICING_MODELS_BY_KIND, fn (array $models) => in_array($model, $models, true))));
@endphp

<div data-package-form>
<x-admin::translatable-field name="name" :label="__('Name')" :value="$package?->getTranslations('name') ?? []" :required="true" />
<x-admin::translatable-field name="description" :label="__('Description')" :value="$package?->getTranslations('description') ?? []" :textarea="true" />

<div class="admin-form-field">
    <label for="product_category_id">{{ __('Category') }}</label>
    <select id="product_category_id" name="product_category_id" required data-package-category>
        @foreach ($productCategories as $productCategory)
            <option value="{{ $productCategory->id }}" data-kind="{{ $productCategory->kind }}" @selected($selectedCategoryId === (string) $productCategory->id)>
                {{ $productCategory->name }}{{ $productCategory->is_active ? '' : ' ('.__('hidden').')' }}
            </option>
        @endforeach
    </select>
    <p class="admin-hint">
        {{ __('The category decides how the package is booked.') }}
        <a href="{{ route('admin.package-categories.index') }}">{{ __('Manage categories') }}</a>
    </p>
    <p class="admin-hint" data-package-kind="activity" @if ($selectedKind !== 'activity') hidden @endif>
        {{ __('Activity packages are shown on the website with their prices and a "Book on WhatsApp" button — online booking for activities comes later.') }}
    </p>
</div>

<div class="admin-form-field">
    <label for="pricing_model">{{ __('Pricing type') }}</label>
    <select id="pricing_model" name="pricing_model" required data-pricing-model>
        @foreach ($pricingModels as $value => $label)
            <option value="{{ $value }}" data-kinds="{{ $kindsFor($value) }}" @selected($selectedModel === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <p class="admin-hint">{{ __('Set the actual prices under "Pricing tiers" after saving. For hourly packages the tiers count hours; for per-person packages they count people.') }}</p>
</div>

<div data-pricing-hidden-for="per_hour per_person" @if (in_array($selectedModel, ['per_hour', 'per_person'], true)) hidden @endif>
    <div class="admin-form-field">
        <label for="min_days">{{ __('Minimum days (nights for a stay)') }}</label>
        <input type="number" id="min_days" name="min_days" value="{{ old('min_days', $package?->min_days ?? 1) }}" min="1" required>
    </div>

    <div class="admin-form-field">
        <label for="max_days">{{ __('Maximum days / nights (blank = unlimited)') }}</label>
        <input type="number" id="max_days" name="max_days" value="{{ old('max_days', $package?->max_days) }}" min="1">
    </div>
</div>

<fieldset class="admin-form-field admin-location" data-pricing-shown-for="per_hour" @if ($selectedModel !== 'per_hour') hidden @endif>
    <legend>{{ __('Hourly rental') }}</legend>
    <p class="admin-hint" style="margin-top:0;">{{ __('Customers pick a start time and how many hours. Rentals start from :first:00 and must be back by :last:00 the same day.', ['first' => sprintf('%02d', config('booking.hourly.first_start_hour')), 'last' => sprintf('%02d', config('booking.hourly.last_return_hour'))]) }}</p>
    <div class="admin-location__coords">
        <div>
            <label for="min_hours">{{ __('Minimum hours') }}</label>
            <input type="number" id="min_hours" name="min_hours" value="{{ old('min_hours', $package?->min_hours ?? 2) }}" min="1" max="24">
        </div>
        <div>
            <label for="max_hours">{{ __('Maximum hours (blank = until closing)') }}</label>
            <input type="number" id="max_hours" name="max_hours" value="{{ old('max_hours', $package?->max_hours) }}" min="1" max="24">
        </div>
    </div>
</fieldset>

<fieldset class="admin-form-field admin-location" data-package-kind="vehicle" @if ($selectedKind !== 'vehicle') hidden @endif>
    <legend>{{ __('Distance (km bundle)') }}</legend>
    <p class="admin-hint" style="margin-top:0;">{{ __('Leave both blank for unlimited km. With an extra-km rate, staff record the odometer at pickup and return, and km over the allowance are added to the booking automatically.') }}</p>
    <div class="admin-location__coords">
        <div>
            <label for="included_km">{{ __('Included km') }}</label>
            <input type="number" id="included_km" name="included_km" value="{{ old('included_km', $package?->included_km) }}" min="0">
        </div>
        <div>
            <label for="extra_km_rate">{{ __('Price per extra km') }}</label>
            <input type="number" id="extra_km_rate" name="extra_km_rate" value="{{ old('extra_km_rate', $package?->extra_km_rate) }}" min="0" step="0.01">
        </div>
    </div>
    <input type="hidden" name="included_km_per_day" value="0">
    <label style="font-weight:400;margin-top:0.5rem;display:block;">
        <input type="checkbox" name="included_km_per_day" value="1" @checked(old('included_km_per_day', $package?->included_km_per_day ?? true))>
        {{ __('Included km is per day (e.g. 100 km/day → 300 km for 3 days). Untick for a total for the whole rental.') }}
    </label>
</fieldset>

<div class="admin-form-field">
    <label for="deposit_amount">{{ __('Deposit amount') }}</label>
    <input type="number" id="deposit_amount" name="deposit_amount" value="{{ old('deposit_amount', $package?->deposit_amount) }}" min="0" step="0.01">
</div>

<div class="admin-form-field">
    <label><input type="checkbox" name="deposit_is_percent" value="1" @checked(old('deposit_is_percent', $package?->deposit_is_percent))> {{ __('Deposit is a percentage, not a fixed amount') }}</label>
</div>

<div class="admin-form-field">
    <label for="valid_from">{{ __('Valid from (blank = always)') }}</label>
    <input type="date" id="valid_from" name="valid_from" value="{{ old('valid_from', $package?->valid_from?->toDateString()) }}">
</div>

<div class="admin-form-field">
    <label for="valid_until">{{ __('Valid until (blank = always)') }}</label>
    <input type="date" id="valid_until" name="valid_until" value="{{ old('valid_until', $package?->valid_until?->toDateString()) }}">
</div>

<div class="admin-form-field">
    <label for="sort_order">{{ __('Sort order') }}</label>
    <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $package?->sort_order ?? 0) }}" min="0" required>
</div>

<div class="admin-form-field">
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $package?->is_active ?? true))> {{ __('Active') }}</label>
</div>

<div class="admin-form-field">
    <label><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $package?->is_featured))> {{ __('Featured on homepage') }}</label>
</div>

<fieldset class="admin-form-field" data-package-kind="vehicle stay" @if ($selectedKind === 'activity') hidden @endif>
    <legend style="font-weight:600;margin-bottom:0.5rem;">{{ __('Restrict to unit types (leave empty = any unit of the booking style)') }}</legend>
    @foreach ($categories as $category)
        <label style="display:block;font-weight:400;">
            <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', $selectedCategoryIds ?? []), true))>
            {{ $category->name }} <span class="admin-badge {{ $category->isStay() ? 'admin-badge--stay' : '' }}">{{ $category->isStay() ? __('Stay') : __('Tuk tuk') }}</span>
        </label>
    @endforeach
</fieldset>

<fieldset class="admin-form-field" data-package-kind="vehicle stay" @if ($selectedKind === 'activity') hidden @endif>
    <legend style="font-weight:600;margin-bottom:0.5rem;">{{ __('Restrict to specific units (leave empty = any eligible unit) — e.g. the cabanas this surf package is sold with') }}</legend>
    @foreach ($vehicles as $vehicle)
        <label style="display:block;font-weight:400;">
            <input type="checkbox" name="vehicle_ids[]" value="{{ $vehicle->id }}" @checked(in_array($vehicle->id, old('vehicle_ids', $selectedVehicleIds ?? []), true))>
            {{ $vehicle->adminLabel() }} <span class="admin-badge {{ $vehicle->isStay() ? 'admin-badge--stay' : '' }}">{{ $vehicle->isStay() ? __('Stay') : __('Tuk tuk') }}</span>
        </label>
    @endforeach
</fieldset>

<div class="admin-form-field">
    <label for="images">{{ $package ? __('Add more photos') : __('Photos') }}</label>
    <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
    <p class="admin-hint">{{ __('Shown on the package card and detail page — the first photo uploaded (or marked primary) is used on the homepage and package listing.') }}</p>
</div>
</div>

<script src="{{ asset_v('assets/js/admin-package-form.js') }}" defer nonce="{{ csp_nonce() }}"></script>
