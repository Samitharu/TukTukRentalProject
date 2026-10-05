@php
    $category = $category ?? null;
    $currentKind = old('kind', $category?->kind ?? \Modules\Package\Models\Package::KIND_VEHICLE);
@endphp

<x-admin::translatable-field name="name" :label="__('Name')" :value="$category?->getTranslations('name') ?? []" :required="true" />
<x-admin::translatable-field name="description" :label="__('Description (shown at the top of the category page)')" :value="$category?->getTranslations('description') ?? []" :textarea="true" />

<div class="admin-form-field">
    <label for="kind">{{ __('Booking style') }}</label>
    @if ($hasPackages ?? false)
        <input type="hidden" name="kind" value="{{ $category->kind }}">
    @endif
    <select id="kind" name="kind" required @disabled($hasPackages ?? false)>
        <option value="vehicle" @selected($currentKind === 'vehicle')>{{ __('Rental — customer picks dates and pickup/delivery; a tuk tuk is assigned') }}</option>
        <option value="stay" @selected($currentKind === 'stay')>{{ __('Stay — check-in / check-out; a cabana or room is assigned') }}</option>
        <option value="activity" @selected($currentKind === 'activity')>{{ __('Activity — surfing, kitesurfing…; shown with prices, booked on WhatsApp') }}</option>
    </select>
    <p class="admin-hint">
        @if ($hasPackages ?? false)
            {{ __('This category has packages, so its booking style is locked.') }}
        @else
            {{ __('Decides how customers book every package in this category, and which pricing types those packages can use.') }}
        @endif
    </p>
</div>

<div class="admin-form-field">
    <label for="image">{{ $category?->image_path ? __('Replace cover photo') : __('Cover photo (optional)') }}</label>
    @if ($category?->image_path)
        <img src="{{ $category->imageUrl() }}" alt="" style="display:block;max-width:240px;border-radius:6px;margin-bottom:0.5rem;">
        <label style="font-weight:400;"><input type="checkbox" name="remove_image" value="1"> {{ __('Remove the current photo') }}</label>
    @endif
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
    <p class="admin-hint">{{ __('Shown on the category page and its tab on the packages page.') }}</p>
</div>

<div class="admin-form-field">
    <label for="sort_order">{{ __('Sort order') }}</label>
    <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $category?->sort_order ?? 0) }}" min="0" required>
</div>

<div class="admin-form-field">
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category?->is_active ?? true))> {{ __('Active (shown on the website)') }}</label>
</div>
