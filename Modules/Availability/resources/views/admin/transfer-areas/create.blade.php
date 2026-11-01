<x-admin::layouts.app :title="__('Add area')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.transfer-areas.store') }}" novalidate>
            @csrf

            @include('availability::admin.transfer-areas._form-fields', ['area' => null])

            <button type="submit" class="admin-btn">{{ __('Add area') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
