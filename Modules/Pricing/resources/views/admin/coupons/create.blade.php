<x-admin::layouts.app :title="__('Add coupon')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.coupons.store') }}" novalidate>
            @csrf

            <div class="admin-form-field">
                <label for="code">{{ __('Code') }}</label>
                <input type="text" id="code" name="code" value="{{ old('code') }}" required>
            </div>

            <div class="admin-form-field">
                <label for="type">{{ __('Type') }}</label>
                <select id="type" name="type" required>
                    <option value="percent">{{ __('Percent off') }}</option>
                    <option value="fixed">{{ __('Fixed amount off') }}</option>
                </select>
            </div>

            <div class="admin-form-field">
                <label for="value">{{ __('Value') }}</label>
                <input type="number" id="value" name="value" step="0.01" min="0" required>
            </div>

            <div class="admin-form-field">
                <label for="min_days">{{ __('Minimum rental days (optional)') }}</label>
                <input type="number" id="min_days" name="min_days" min="1">
            </div>

            <div class="admin-form-field">
                <label for="valid_from">{{ __('Valid from') }}</label>
                <input type="date" id="valid_from" name="valid_from">
            </div>

            <div class="admin-form-field">
                <label for="valid_until">{{ __('Valid until') }}</label>
                <input type="date" id="valid_until" name="valid_until">
            </div>

            <div class="admin-form-field">
                <label for="usage_limit">{{ __('Usage limit (optional)') }}</label>
                <input type="number" id="usage_limit" name="usage_limit" min="1">
            </div>

            <div class="admin-form-field">
                <label><input type="checkbox" name="is_active" value="1" checked> {{ __('Active') }}</label>
            </div>

            <button type="submit" class="admin-btn">{{ __('Add coupon') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
