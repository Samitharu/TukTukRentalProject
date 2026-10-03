<x-admin::layouts.app :title="__('Add package')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.packages.store') }}" enctype="multipart/form-data" novalidate>
            @csrf
            @include('package::admin.packages._form-fields')
            <button type="submit" class="admin-btn">{{ __('Create package') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
