{{-- Shared by create/edit; $area is null on create. --}}
<x-admin::translatable-field name="name" :label="__('Name')" :value="$area?->getTranslations('name') ?? []" :required="true" />

<div class="admin-form-field">
    <label for="price">{{ __('Trip price') }} ({{ config('pricing.default_currency') }})</label>
    <input type="number" id="price" name="price" value="{{ old('price', $area?->price ?? 0) }}" min="0" step="0.01" required>
    <p class="admin-hint">{{ __('Added to the airport package\'s own price (use the package price for a bigger vehicle\'s surcharge).') }}</p>
</div>

<div class="admin-form-field">
    <label for="duration_minutes">{{ __('Trip duration (minutes)') }}</label>
    <input type="number" id="duration_minutes" name="duration_minutes" value="{{ old('duration_minutes', $area?->duration_minutes ?? 120) }}" min="15" max="960" required>
    <p class="admin-hint">{{ __('How long the vehicle is away for one trip, there and back. That time is blocked on the vehicle; a further :buffer minutes are kept free between trips.', ['buffer' => config('booking.transfer_buffer_minutes')]) }}</p>
</div>

<div class="admin-form-field">
    <label for="sort_order">{{ __('Sort order') }}</label>
    <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $area?->sort_order ?? 0) }}" min="0" required>
</div>

<div class="admin-form-field">
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $area?->is_active ?? true))> {{ __('Active') }}</label>
</div>
