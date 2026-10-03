<x-admin::layouts.app :title="__('Edit FAQ')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.faqs.update', $faq) }}" novalidate>
            @csrf @method('PUT')
            @include('cms::admin.faqs._form-fields')
            <button type="submit" class="admin-btn">{{ __('Save changes') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
