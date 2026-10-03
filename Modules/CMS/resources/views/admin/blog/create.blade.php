<x-admin::layouts.app :title="__('Add post')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.blog.store') }}" novalidate>
            @csrf
            @include('cms::admin.blog._form-fields')
            <button type="submit" class="admin-btn">{{ __('Create post') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
