<x-admin::layouts.app :title="__('Customers')">
    <form method="GET" class="admin-card">
        <div class="admin-form-field" style="margin-bottom:0;">
            <label for="q">{{ __('Search by name or email') }}</label>
            <input type="text" id="q" name="q" value="{{ $search }}">
        </div>
        <button type="submit" class="admin-btn admin-btn--secondary" style="margin-top:0.5rem;">{{ __('Search') }}</button>
    </form>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th>{{ __('Nationality') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($customers as $customer)
                    <tr>
                        <td>{{ $customer->full_name }}</td>
                        <td>{{ $customer->email }}</td>
                        <td>{{ $customer->phone }}</td>
                        <td>{{ $customer->nationality }}</td>
                        <td><a href="{{ route('admin.customers.show', $customer) }}">{{ __('View') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $customers->links() }}
</x-admin::layouts.app>
