<x-admin::layouts.app :title="__('Currencies')">
    <div class="admin-card">
        <h2>{{ __('Add currency') }}</h2>
        <form method="POST" action="{{ route('admin.currencies.store') }}" novalidate>
            @csrf
            <div class="admin-form-field">
                <label for="code">{{ __('ISO code (e.g. EUR)') }}</label>
                <input type="text" id="code" name="code" maxlength="3" required>
            </div>
            <div class="admin-form-field">
                <label for="symbol">{{ __('Symbol') }}</label>
                <input type="text" id="symbol" name="symbol" maxlength="5" required>
            </div>
            <div class="admin-form-field">
                <label for="decimal_places">{{ __('Decimal places') }}</label>
                <input type="number" id="decimal_places" name="decimal_places" value="2" min="0" max="4" required>
            </div>
            <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Add currency') }}</button>
        </form>
    </div>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Symbol') }}</th>
                    <th>{{ __('Base') }}</th>
                    <th>{{ __('Latest rate') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($currencies as $currency)
                    <tr>
                        <td>{{ $currency->code }}</td>
                        <td>{{ $currency->symbol }}</td>
                        <td>{{ $currency->is_base ? __('Yes') : __('No') }}</td>
                        <td>{{ $currency->latestRate() ?? __('not set') }}</td>
                        <td>
                            @unless ($currency->is_base)
                                <form method="POST" action="{{ route('admin.currencies.rate.update', $currency) }}" style="display:inline;">
                                    @csrf
                                    <input type="number" name="rate" step="0.000001" min="0.000001" placeholder="{{ __('rate') }}" style="width:6rem;min-height:32px;" required>
                                    <button type="submit" class="admin-btn admin-btn--secondary" style="min-height:32px;padding:0.25rem 0.5rem;">{{ __('Update') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.currencies.make-base', $currency) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" style="background:none;border:none;color:#0f6b4c;cursor:pointer;">{{ __('Make base') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.currencies.destroy', $currency) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this currency?') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background:none;border:none;color:#b3261e;cursor:pointer;">{{ __('Remove') }}</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin::layouts.app>
