<x-admin::layouts.app :title="__('Packages')">
    <p><a href="{{ route('admin.packages.create') }}" class="admin-btn">{{ __('Add package') }}</a></p>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Pricing model') }}</th>
                    <th>{{ __('Tiers') }}</th>
                    <th>{{ __('Active') }}</th>
                    <th>{{ __('Featured') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($packages as $package)
                    <tr>
                        <td>{{ $package->name }}</td>
                        <td><span class="admin-badge {{ $package->isStay() ? 'admin-badge--stay' : '' }}">{{ $package->isStay() ? __('Stay') : __('Tuk tuk') }}</span></td>
                        <td>{{ $package->pricing_model }}</td>
                        <td>{{ $package->pricing_tiers_count }}</td>
                        <td>{{ $package->is_active ? __('Yes') : __('No') }}</td>
                        <td>{{ $package->is_featured ? __('Yes') : __('No') }}</td>
                        <td>
                            <a href="{{ route('admin.packages.edit', $package) }}">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.packages.destroy', $package) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this package?') }}');">
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
