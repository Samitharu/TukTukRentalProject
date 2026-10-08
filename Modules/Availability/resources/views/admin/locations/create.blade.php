<x-admin::layouts.app :title="__('Add location')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.locations.store') }}" novalidate>
            @csrf

            @include('availability::admin.locations._form-fields', ['location' => null])

            <button type="submit" class="admin-btn">{{ __('Add location') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
