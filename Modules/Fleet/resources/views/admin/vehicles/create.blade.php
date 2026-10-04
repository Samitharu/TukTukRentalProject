<x-admin::layouts.app :title="__('Add unit')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.fleet.vehicles.store') }}" enctype="multipart/form-data" novalidate data-unit-form>
            @csrf

            @include('fleet::admin.vehicles._form-fields')

            <button type="submit" class="admin-btn">{{ __('Add unit') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
