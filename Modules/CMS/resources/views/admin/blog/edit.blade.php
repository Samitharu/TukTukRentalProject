<x-admin::layouts.app :title="__('Edit post')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.blog.update', $post) }}" novalidate>
            @csrf @method('PUT')
            @include('cms::admin.blog._form-fields')
            <button type="submit" class="admin-btn">{{ __('Save changes') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
