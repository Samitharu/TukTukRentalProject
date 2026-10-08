<x-admin::layouts.app :title="__('Edit location')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.locations.update', $location) }}" novalidate>
            @csrf
            @method('PUT')

            @include('availability::admin.locations._form-fields', ['location' => $location])

            <button type="submit" class="admin-btn">{{ __('Save changes') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
