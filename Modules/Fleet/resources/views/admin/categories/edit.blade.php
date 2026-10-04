<x-admin::layouts.app :title="__('Edit category')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.fleet.categories.update', $category) }}" novalidate>
            @csrf
            @method('PUT')

            <x-admin::translatable-field name="name" :label="__('Name')" :value="$category->getTranslations('name')" :required="true" />
            <x-admin::translatable-field name="description" :label="__('Description')" :value="$category->getTranslations('description')" :textarea="true" />

            @include('fleet::admin.categories._kind-field', ['current' => old('kind', $category->kind)])

            <div class="admin-form-field">
                <label for="icon">{{ __('Icon (optional keyword)') }}</label>
                <input type="text" id="icon" name="icon" value="{{ old('icon', $category->icon) }}">
            </div>

            <div class="admin-form-field">
                <label for="sort_order">{{ __('Sort order') }}</label>
                <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}" min="0" required>
            </div>

            <div class="admin-form-field">
                <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active))> {{ __('Active') }}</label>
            </div>

            <button type="submit" class="admin-btn">{{ __('Save changes') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
