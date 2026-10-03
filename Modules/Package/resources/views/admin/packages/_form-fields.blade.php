@php
    $package = $package ?? null;
@endphp

<x-admin::translatable-field name="name" :label="__('Name')" :value="$package?->getTranslations('name') ?? []" :required="true" />
<x-admin::translatable-field name="description" :label="__('Description')" :value="$package?->getTranslations('description') ?? []" :textarea="true" />

<div class="admin-form-field">
    <label for="pricing_model">{{ __('Pricing model') }}</label>
    <select id="pricing_model" name="pricing_model" required>
        @foreach (['per_day' => __('Per day'), 'per_week' => __('Per week'), 'per_month' => __('Per month'), 'fixed_bundle' => __('Fixed bundle'), 'tiered' => __('Tiered by day count')] as $value => $label)
            <option value="{{ $value }}" @selected(old('pricing_model', $package?->pricing_model ?? 'tiered') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div class="admin-form-field">
    <label for="min_days">{{ __('Minimum rental days') }}</label>
    <input type="number" id="min_days" name="min_days" value="{{ old('min_days', $package?->min_days ?? 1) }}" min="1" required>
</div>

<div class="admin-form-field">
    <label for="max_days">{{ __('Maximum rental days (blank = unlimited)') }}</label>
    <input type="number" id="max_days" name="max_days" value="{{ old('max_days', $package?->max_days) }}" min="1">
</div>

<div class="admin-form-field">
    <label for="included_km">{{ __('Included km per day (blank = unlimited)') }}</label>
    <input type="number" id="included_km" name="included_km" value="{{ old('included_km', $package?->included_km) }}" min="0">
</div>

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

<fieldset class="admin-form-field">
    <legend style="font-weight:600;margin-bottom:0.5rem;">{{ __('Restrict to vehicle categories (leave empty = any category)') }}</legend>
    @foreach ($categories as $category)
        <label style="display:block;font-weight:400;">
            <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', $selectedCategoryIds ?? []), true))>
            {{ $category->name }}
        </label>
    @endforeach
</fieldset>

<fieldset class="admin-form-field">
    <legend style="font-weight:600;margin-bottom:0.5rem;">{{ __('Restrict to specific vehicles (leave empty = any eligible vehicle)') }}</legend>
    @foreach ($vehicles as $vehicle)
        <label style="display:block;font-weight:400;">
            <input type="checkbox" name="vehicle_ids[]" value="{{ $vehicle->id }}" @checked(in_array($vehicle->id, old('vehicle_ids', $selectedVehicleIds ?? []), true))>
            {{ $vehicle->plate_no }} — {{ $vehicle->name }}
        </label>
    @endforeach
</fieldset>

<div class="admin-form-field">
    <label for="images">{{ $package ? __('Add more photos') : __('Photos') }}</label>
    <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
    <p style="color:#5b6b64;font-size:0.85em;margin-top:0.25rem;">{{ __('Shown on the package card and detail page — the first photo uploaded (or marked primary) is used on the homepage and package listing.') }}</p>
</div>
