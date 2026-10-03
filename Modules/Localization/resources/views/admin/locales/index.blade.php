<x-admin::layouts.app :title="__('Locales')">
    <p><a href="{{ route('admin.locales.create') }}" class="admin-btn">{{ __('Add locale') }}</a></p>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Native name') }}</th>
                    <th>{{ __('Default') }}</th>
                    <th>{{ __('Active') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($locales as $locale)
                    <tr>
                        <td><code>{{ $locale->code }}</code></td>
                        <td>{{ $locale->name }}</td>
                        <td>{{ $locale->native_name }}</td>
                        <td>
                            @if ($locale->is_default)
                                {{ __('Yes') }}
                            @else
                                <form method="POST" action="{{ route('admin.locales.make-default', $locale) }}">
                                    @csrf
                                    <button type="submit" style="background:none;border:none;color:#0f6b4c;cursor:pointer;padding:0;">{{ __('Make default') }}</button>
                                </form>
                            @endif
                        </td>
                        <td>{{ $locale->is_active ? __('Yes') : __('No') }}</td>
                        <td>
                            <a href="{{ route('admin.locales.edit', $locale) }}">{{ __('Edit') }}</a>
                            @if (! $locale->is_default)
                                <form method="POST" action="{{ route('admin.locales.destroy', $locale) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this locale?') }}');">
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
