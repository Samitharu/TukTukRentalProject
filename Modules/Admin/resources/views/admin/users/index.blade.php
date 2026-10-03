<x-admin::layouts.app :title="__('Users')">
    <p><a href="{{ route('admin.users.create') }}" class="admin-btn">{{ __('Add user') }}</a></p>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->roles->pluck('name')->implode(', ') }}</td>
                        <td>{{ $user->is_active ? __('Active') : __('Inactive') }}</td>
                        <td>
                            <a href="{{ route('admin.users.edit', $user) }}">{{ __('Edit') }}</a>
                            @can('delete', $user)
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this user?') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background:none;border:none;color:#b3261e;cursor:pointer;padding:0 0 0 0.5rem;">{{ __('Remove') }}</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</x-admin::layouts.app>
