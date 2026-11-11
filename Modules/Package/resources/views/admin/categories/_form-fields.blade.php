@php
    $category = $category ?? null;
    $currentKind = old('kind', $category?->kind ?? \Modules\Package\Models\Package::KIND_VEHICLE);
@endphp

<x-admin::translatable-field name="name" :label="__('Name')" :value="$category?->getTranslations('name') ?? []" :required="true" />

<div class="admin-form-field">
    <label for="kind">{{ __('How customers book') }}</label>
    @if ($hasPackages ?? false)
        <input type="hidden" name="kind" value="{{ $category->kind }}">
    @endif
    <select id="kind" name="kind" required @disabled($hasPackages ?? false)>
        <option value="vehicle" @selected($currentKind === 'vehicle')>{{ __('Rental — picks dates, collects at the office') }}</option>
        <option value="stay" @selected($currentKind === 'stay')>{{ __('Stay — picks check-in and check-out') }}</option>
        <option value="transfer" @selected($currentKind === 'transfer')>{{ __('Airport pickup — picks direction, time, flight and area') }}</option>
        <option value="activity" @selected($currentKind === 'activity')>{{ __('Activity — sees prices, books on WhatsApp') }}</option>
        <option value="tour" @selected($currentKind === 'tour')>{{ __('Tour plan — picks dates, charged per day') }}</option>
    </select>
    @if ($hasPackages ?? false)
        <p class="admin-hint">{{ __('This category has packages, so this is locked.') }}</p>
    @endif
</div>

<div class="admin-form-field">
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category?->is_active ?? true))> {{ __('Active (shown on the website)') }}</label>
</div>

<details class="admin-form-field" @if ($errors->has('description.*') || $errors->hasAny(['image', 'sort_order'])) open @endif>
    <summary style="cursor:pointer;font-weight:600;">{{ __('More options (optional)') }}</summary>

    <div style="margin-top:0.75rem;">
        <x-admin::translatable-field name="description" :label="__('Description (shown at the top of the category page)')" :value="$category?->getTranslations('description') ?? []" :textarea="true" />

        <div class="admin-form-field">
            <label for="image">{{ $category?->image_path ? __('Replace cover photo') : __('Cover photo') }}</label>
            @if ($category?->image_path)
                <img src="{{ $category->imageUrl() }}" alt="" style="display:block;max-width:240px;border-radius:6px;margin-bottom:0.5rem;">
                <label style="font-weight:400;"><input type="checkbox" name="remove_image" value="1"> {{ __('Remove the current photo') }}</label>
            @endif
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
            <p class="admin-hint">{{ __('Shown on the category page and its tab on the packages page.') }}</p>
        </div>

        <div class="admin-form-field">
            <label for="sort_order">{{ __('Position in the menu') }}</label>
            <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $category?->sort_order) }}" min="0">
            <p class="admin-hint">{{ __('Lower numbers come first. Leave empty to keep it where it is, or add a new one at the end.') }}</p>
        </div>
    </div>
</details>
