<x-admin::layouts.app :title="__('Packages')">
    <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;margin-bottom:1rem;">
        @can('create', \Modules\Package\Models\Package::class)
            <a href="{{ route('admin.packages.create', array_filter(['category' => $selectedCategoryId])) }}" class="admin-btn">{{ __('Add package') }}</a>
        @endcan
        <form method="GET" action="{{ route('admin.packages.index') }}" style="display:flex;gap:0.5rem;align-items:center;">
            <label for="category" style="margin:0;">{{ __('Category') }}</label>
            <select id="category" name="category">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($productCategories as $productCategory)
                    <option value="{{ $productCategory->id }}" @selected($selectedCategoryId === $productCategory->id)>{{ $productCategory->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Filter') }}</button>
        </form>
    </div>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th>{{ __('Pricing type') }}</th>
                    <th>{{ __('Tiers') }}</th>
                    <th>{{ __('Active') }}</th>
                    <th>{{ __('Featured') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($packages as $package)
                    <tr>
                        <td>{{ $package->name }}</td>
                        <td>
                            {{ $package->productCategory?->name ?? '—' }}
                            @include('package::admin._kind-badge', ['kind' => $package->kind])
                        </td>
                        <td>
                            {{ str_replace('_', ' ', $package->pricing_model) }}
                            @if ($package->hasKmAllowance())
                                <span class="admin-hint" style="display:block;margin:0;">{{ number_format($package->included_km) }} km{{ $package->included_km_per_day ? '/'.__('day') : '' }} + {{ $package->extra_km_rate }}/km</span>
                            @endif
                        </td>
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
                @empty
                    <tr><td colspan="7">{{ __('No packages yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin::layouts.app>
