<x-admin::layouts.app :title="__('Pages')">
    <p><a href="{{ route('admin.pages.create') }}" class="admin-btn">{{ __('Add page') }}</a></p>
    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Template') }}</th><th>{{ __('Published') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($pages as $page)
                    <tr>
                        <td>{{ $page->title }}</td>
                        <td>{{ $page->template }}</td>
                        <td>{{ $page->is_published ? __('Yes') : __('No') }}</td>
                        <td>
                            <a href="{{ route('admin.pages.edit', $page) }}">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this page?') }}');">
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
