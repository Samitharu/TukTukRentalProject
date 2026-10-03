<x-admin::layouts.app :title="__('Add category')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.fleet.categories.store') }}" novalidate>
            @csrf

            <x-admin::translatable-field name="name" :label="__('Name')" :required="true" />
            <x-admin::translatable-field name="description" :label="__('Description')" :textarea="true" />

            <div class="admin-form-field">
                <label for="icon">{{ __('Icon (optional keyword)') }}</label>
                <input type="text" id="icon" name="icon" value="{{ old('icon') }}">
            </div>

            <div class="admin-form-field">
                <label for="sort_order">{{ __('Sort order') }}</label>
                <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" required>
            </div>

            <div class="admin-form-field">
                <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> {{ __('Active') }}</label>
            </div>

            <button type="submit" class="admin-btn">{{ __('Add category') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
