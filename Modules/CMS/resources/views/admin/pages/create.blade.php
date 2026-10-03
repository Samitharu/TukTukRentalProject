<x-admin::layouts.app :title="__('Add page')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.pages.store') }}" novalidate>
            @csrf
            @include('cms::admin.pages._form-fields')
            <button type="submit" class="admin-btn">{{ __('Create page') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
