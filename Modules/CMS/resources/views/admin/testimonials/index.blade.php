<x-admin::layouts.app :title="__('Testimonials')">
    <p><a href="{{ route('admin.testimonials.create') }}" class="admin-btn">{{ __('Add testimonial') }}</a></p>
    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Rating') }}</th><th>{{ __('Content') }}</th><th>{{ __('Approved') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($testimonials as $t)
                    <tr>
                        <td>{{ $t->customer_name }} ({{ $t->country }})</td>
                        <td>{{ $t->rating }}/5</td>
                        <td>{{ \Illuminate\Support\Str::limit($t->content, 60) }}</td>
                        <td>{{ $t->is_approved ? __('Yes') : __('No') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.testimonials.toggle-approval', $t) }}" style="display:inline;">
                                @csrf
                                <button type="submit" style="background:none;border:none;color:#0f6b4c;cursor:pointer;">{{ $t->is_approved ? __('Unapprove') : __('Approve') }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.testimonials.destroy', $t) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove?') }}');">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:#b3261e;cursor:pointer;">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin::layouts.app>
