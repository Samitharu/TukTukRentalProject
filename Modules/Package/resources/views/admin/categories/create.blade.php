<x-admin::layouts.app :title="__('Add category')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.package-categories.store') }}" enctype="multipart/form-data" novalidate>
            @csrf
            @include('package::admin.categories._form-fields')
            <button type="submit" class="admin-btn">{{ __('Create category') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
