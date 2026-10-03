<x-admin::layouts.app :title="__('Add add-on')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.addons.store') }}" novalidate>
            @csrf

            <x-admin::translatable-field name="name" :label="__('Name')" :required="true" />
            <x-admin::translatable-field name="description" :label="__('Description')" :textarea="true" />

            <div class="admin-form-field">
                <label for="price">{{ __('Price') }}</label>
                <input type="number" id="price" name="price" min="0" step="0.01" required>
            </div>

            <div class="admin-form-field">
                <label for="pricing_unit">{{ __('Pricing unit') }}</label>
                <select id="pricing_unit" name="pricing_unit" required>
                    <option value="flat">{{ __('Flat (one-time)') }}</option>
                    <option value="per_day">{{ __('Per day') }}</option>
                </select>
            </div>

            <div class="admin-form-field">
                <label for="max_quantity">{{ __('Max quantity per booking') }}</label>
                <input type="number" id="max_quantity" name="max_quantity" value="1" min="1" max="20" required>
            </div>

            <div class="admin-form-field">
                <label for="sort_order">{{ __('Sort order') }}</label>
                <input type="number" id="sort_order" name="sort_order" value="0" min="0" required>
            </div>

            <div class="admin-form-field">
                <label><input type="checkbox" name="is_active" value="1" checked> {{ __('Active') }}</label>
            </div>

            <button type="submit" class="admin-btn">{{ __('Add add-on') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
