<x-admin::layouts.app :title="__('Blog')">
    <p><a href="{{ route('admin.blog.create') }}" class="admin-btn">{{ __('Add post') }}</a></p>
    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Published') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($posts as $post)
                    <tr>
                        <td>{{ $post->title }}</td>
                        <td>{{ $post->is_published ? __('Yes') : __('No') }}</td>
                        <td>
                            <a href="{{ route('admin.blog.edit', $post) }}">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.blog.destroy', $post) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this post?') }}');">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:#b3261e;cursor:pointer;padding:0 0 0 0.5rem;">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin::layouts.app>
