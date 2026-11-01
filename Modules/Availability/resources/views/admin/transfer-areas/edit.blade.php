<x-admin::layouts.app :title="__('Edit area')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.transfer-areas.update', $area) }}" novalidate>
            @csrf
            @method('PUT')

            @include('availability::admin.transfer-areas._form-fields', ['area' => $area])

            <button type="submit" class="admin-btn">{{ __('Save changes') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
