<x-admin::layouts.app :title="__('Package Categories')">
    <p class="admin-hint" style="margin-bottom:1rem;">{{ __('Your lines of business — each one gets its own page on the website, and every package belongs to one.') }}</p>

    @can('create', \Modules\Package\Models\ProductCategory::class)
        <p><a href="{{ route('admin.package-categories.create') }}" class="admin-btn">{{ __('Add category') }}</a></p>
    @endcan

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Booking style') }}</th>
                    <th>{{ __('Packages') }}</th>
                    <th>{{ __('Active') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td>@include('package::admin._kind-badge', ['kind' => $category->kind])</td>
                        <td><a href="{{ route('admin.packages.index', ['category' => $category->id]) }}">{{ $category->packages_count }}</a></td>
                        <td>{{ $category->is_active ? __('Yes') : __('No') }}</td>
                        <td>
                            @can('update', $category)
                                <a href="{{ route('admin.package-categories.edit', $category) }}">{{ __('Edit') }}</a>
                            @endcan
                            @can('delete', $category)
                                <form method="POST" action="{{ route('admin.package-categories.destroy', $category) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this category?') }}');">
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
</x-admin::layouts.app>
