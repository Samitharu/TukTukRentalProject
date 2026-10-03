<x-admin::layouts.app :title="__('Add-ons')">
    <p><a href="{{ route('admin.addons.create') }}" class="admin-btn">{{ __('Add add-on') }}</a></p>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Price') }}</th>
                    <th>{{ __('Unit') }}</th>
                    <th>{{ __('Active') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($addons as $addon)
                    <tr>
                        <td>{{ $addon->name }}</td>
                        <td>{{ $addon->price }}</td>
                        <td>{{ $addon->pricing_unit }}</td>
                        <td>{{ $addon->is_active ? __('Yes') : __('No') }}</td>
                        <td>
                            <a href="{{ route('admin.addons.edit', $addon) }}">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.addons.destroy', $addon) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this add-on?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:#b3261e;cursor:pointer;padding:0 0 0 0.5rem;">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin::layouts.app>
