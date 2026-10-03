<x-admin::layouts.app :title="__('Edit page')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.pages.update', $page) }}" novalidate>
            @csrf @method('PUT')
            @include('cms::admin.pages._form-fields')
            <button type="submit" class="admin-btn">{{ __('Save changes') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
