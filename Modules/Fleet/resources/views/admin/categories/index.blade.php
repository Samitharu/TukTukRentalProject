<x-admin::layouts.app :title="__('Fleet Categories')">
    <p><a href="{{ route('admin.fleet.categories.create') }}" class="admin-btn">{{ __('Add category') }}</a></p>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Vehicles') }}</th>
                    <th>{{ __('Active') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->vehicles_count }}</td>
                        <td>{{ $category->is_active ? __('Yes') : __('No') }}</td>
                        <td>
                            <a href="{{ route('admin.fleet.categories.edit', $category) }}">{{ __('Edit') }}</a>
                            @if ($category->vehicles_count === 0)
                                <form method="POST" action="{{ route('admin.fleet.categories.destroy', $category) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this category?') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background:none;border:none;color:#b3261e;cursor:pointer;padding:0 0 0 0.5rem;">{{ __('Remove') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin::layouts.app>
