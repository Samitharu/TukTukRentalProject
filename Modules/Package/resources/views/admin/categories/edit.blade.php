<x-admin::layouts.app :title="__('Edit category')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.package-categories.update', $category) }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')
            @include('package::admin.categories._form-fields')
            <button type="submit" class="admin-btn">{{ __('Save changes') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
