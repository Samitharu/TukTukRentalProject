<x-admin::layouts.app :title="__('Add FAQ')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.faqs.store') }}" novalidate>
            @csrf
            @include('cms::admin.faqs._form-fields')
            <button type="submit" class="admin-btn">{{ __('Add FAQ') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
