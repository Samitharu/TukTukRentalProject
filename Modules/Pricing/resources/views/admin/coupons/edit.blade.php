<x-admin::layouts.app :title="__('Edit coupon')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.coupons.update', $coupon) }}" novalidate>
            @csrf
            @method('PUT')

            <div class="admin-form-field">
                <label for="code">{{ __('Code') }}</label>
                <input type="text" id="code" name="code" value="{{ old('code', $coupon->code) }}" required>
            </div>

            <div class="admin-form-field">
                <label for="type">{{ __('Type') }}</label>
                <select id="type" name="type" required>
                    <option value="percent" @selected(old('type', $coupon->type) === 'percent')>{{ __('Percent off') }}</option>
                    <option value="fixed" @selected(old('type', $coupon->type) === 'fixed')>{{ __('Fixed amount off') }}</option>
                </select>
            </div>

            <div class="admin-form-field">
                <label for="value">{{ __('Value') }}</label>
                <input type="number" id="value" name="value" value="{{ old('value', $coupon->value) }}" step="0.01" min="0" required>
            </div>

            <div class="admin-form-field">
                <label for="min_days">{{ __('Minimum rental days (optional)') }}</label>
                <input type="number" id="min_days" name="min_days" value="{{ old('min_days', $coupon->min_days) }}" min="1">
            </div>

            <div class="admin-form-field">
                <label for="valid_from">{{ __('Valid from') }}</label>
                <input type="date" id="valid_from" name="valid_from" value="{{ old('valid_from', $coupon->valid_from?->toDateString()) }}">
            </div>

            <div class="admin-form-field">
                <label for="valid_until">{{ __('Valid until') }}</label>
                <input type="date" id="valid_until" name="valid_until" value="{{ old('valid_until', $coupon->valid_until?->toDateString()) }}">
            </div>

            <div class="admin-form-field">
                <label for="usage_limit">{{ __('Usage limit (optional)') }}</label>
                <input type="number" id="usage_limit" name="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit) }}" min="1">
            </div>

            <div class="admin-form-field">
                <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->is_active))> {{ __('Active') }}</label>
            </div>

            <button type="submit" class="admin-btn">{{ __('Save changes') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
