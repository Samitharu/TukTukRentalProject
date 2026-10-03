<x-admin::layouts.app :title="__('Edit locale')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.locales.update', $locale) }}" novalidate>
            @csrf
            @method('PUT')

            <div class="admin-form-field">
                <label>{{ __('Code') }}</label>
                <input type="text" value="{{ $locale->code }}" disabled>
            </div>

            <div class="admin-form-field">
                <label for="name">{{ __('Name') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name', $locale->name) }}" required>
            </div>

            <div class="admin-form-field">
                <label for="native_name">{{ __('Native name') }}</label>
                <input type="text" id="native_name" name="native_name" value="{{ old('native_name', $locale->native_name) }}" required>
            </div>

            <div class="admin-form-field">
                <label for="flag_icon">{{ __('Flag code (ISO 3166-1 country code, e.g. "it" for Italy)') }}</label>
                <input type="text" id="flag_icon" name="flag_icon" value="{{ old('flag_icon', $locale->flag_icon) }}" maxlength="10">
                <p style="color:#5b6b64;font-size:0.85em;margin-top:0.25rem;">{{ __('A matching SVG must exist at public/assets/icons/flags/{code}.svg, or no flag is shown.') }}</p>
            </div>

            <div class="admin-form-field">
                <label for="sort_order">{{ __('Sort order') }}</label>
                <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $locale->sort_order) }}" min="0" required>
            </div>

            <div class="admin-form-field">
                <label>
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $locale->is_active)) @disabled($locale->is_default)>
                    {{ __('Active') }}
                    @if ($locale->is_default)
                        <span style="color:#5b6b64;">({{ __('the default locale is always active') }})</span>
                    @endif
                </label>
            </div>

            <button type="submit" class="admin-btn">{{ __('Save changes') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
