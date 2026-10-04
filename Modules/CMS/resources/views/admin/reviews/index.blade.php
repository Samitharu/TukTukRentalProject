<x-admin::layouts.app :title="__('Reviews')">
    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Rating') }}</th><th>{{ __('Content') }}</th><th>{{ __('Approved') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($reviews as $r)
                    <tr>
                        <td>{{ $r->customer_name }} ({{ $r->country }})</td>
                        <td>{{ $r->rating }}/5</td>
                        <td>{{ \Illuminate\Support\Str::limit($r->content, 60) }}</td>
                        <td>{{ $r->is_approved ? __('Yes') : __('No') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.reviews.toggle-approval', $r) }}" style="display:inline;">
                                @csrf
                                <button type="submit" style="background:none;border:none;color:#0f6b4c;cursor:pointer;">{{ $r->is_approved ? __('Unapprove') : __('Approve') }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.reviews.destroy', $r) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove?') }}');">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:#b3261e;cursor:pointer;">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $reviews->links() }}
</x-admin::layouts.app>
