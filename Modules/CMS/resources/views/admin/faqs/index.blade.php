<x-admin::layouts.app :title="__('FAQs')">
    <p><a href="{{ route('admin.faqs.create') }}" class="admin-btn">{{ __('Add FAQ') }}</a></p>
    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead><tr><th>{{ __('Question') }}</th><th>{{ __('Category') }}</th><th>{{ __('Active') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($faqs as $faq)
                    <tr>
                        <td>{{ $faq->question }}</td>
                        <td>{{ $faq->category }}</td>
                        <td>{{ $faq->is_active ? __('Yes') : __('No') }}</td>
                        <td>
                            <a href="{{ route('admin.faqs.edit', $faq) }}">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this FAQ?') }}');">
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
