<x-admin::layouts.app :title="__('Coupons')">
    <p><a href="{{ route('admin.coupons.create') }}" class="admin-btn">{{ __('Add coupon') }}</a></p>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Value') }}</th>
                    <th>{{ __('Usage') }}</th>
                    <th>{{ __('Active') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($coupons as $coupon)
                    <tr>
                        <td><code>{{ $coupon->code }}</code></td>
                        <td>{{ $coupon->type }}</td>
                        <td>{{ $coupon->value }}{{ $coupon->type === 'percent' ? '%' : '' }}</td>
                        <td>{{ $coupon->usage_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}</td>
                        <td>{{ $coupon->is_active ? __('Yes') : __('No') }}</td>
                        <td>
                            <a href="{{ route('admin.coupons.edit', $coupon) }}">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this coupon?') }}');">
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
