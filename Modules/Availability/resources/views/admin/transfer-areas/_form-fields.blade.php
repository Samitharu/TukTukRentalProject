{{-- Shared by create/edit; $area is null on create. --}}
<x-admin::translatable-field name="name" :label="__('Name')" :value="$area?->getTranslations('name') ?? []" :required="true" />

<div class="admin-form-field">
    <label for="price">{{ __('Trip price') }} (USD)</label>
    <input type="number" id="price" name="price" value="{{ old('price', $area?->price ?? 0) }}" min="0" step="0.01" required>
    @error('price')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
    <label for="price_eur" style="margin-top:0.75rem;">{{ __('Trip price') }} (EUR)</label>
    <input type="number" id="price_eur" name="price_eur" value="{{ old('price_eur', $area?->price_eur) }}" min="0" step="0.01" required>
    @error('price_eur')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
    <p class="admin-hint">{{ __('Added to the airport package\'s own price (use the package price for a bigger vehicle\'s surcharge).') }}</p>
</div>

<div class="admin-form-field">
    <label for="sort_order">{{ __('Sort order') }}</label>
    <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $area?->sort_order ?? 0) }}" min="0" required>
</div>

<div class="admin-form-field">
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $area?->is_active ?? true))> {{ __('Active') }}</label>
</div>
