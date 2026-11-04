<x-admin::layouts.app :title="__('LKR rates')">
    <div class="admin-card">
        <p class="admin-hint" style="margin-top:0;">{{ __('Shows what each booking\'s USD and EUR amounts are in LKR on the staff screens. Customers never see LKR, and prices in USD and EUR do not change with these rates.') }}</p>
        <form method="POST" action="{{ route('admin.currencies.rate.update') }}" novalidate>
            @csrf
            <div class="admin-form-field">
                <label for="rate">{{ __('LKR for 1 USD') }}</label>
                <input type="number" id="rate" name="rate" value="{{ old('rate', $rate) }}" min="0.01" max="999999" step="0.01" required>
                @error('rate')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
            </div>
            <div class="admin-form-field">
                <label for="rate_eur">{{ __('LKR for 1 EUR') }}</label>
                <input type="number" id="rate_eur" name="rate_eur" value="{{ old('rate_eur', $rateEur) }}" min="0.01" max="999999" step="0.01" required>
                @error('rate_eur')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
                @if ($updatedAt)
                    <p class="admin-hint">{{ __('Last changed: :date', ['date' => \Illuminate\Support\Carbon::parse($updatedAt)->format('Y-m-d H:i')]) }}</p>
                @endif
            </div>
            <button type="submit" class="admin-btn">{{ __('Save') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
