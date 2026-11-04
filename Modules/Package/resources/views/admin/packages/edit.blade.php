<x-admin::layouts.app :title="__('Edit package')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.packages.update', $package) }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')
            @include('package::admin.packages._form-fields')
            <button type="submit" class="admin-btn">{{ __('Save changes') }}</button>
        </form>
    </div>

    @if ($package->images->isNotEmpty())
        <div class="admin-card">
            <h2>{{ __('Photos') }}</h2>
            <div class="admin-image-grid">
                @foreach ($package->images as $image)
                    <figure>
                        <img src="{{ asset('storage/'.$image->path) }}" alt="">
                        <form method="POST" action="{{ route('admin.packages.images.destroy', [$package, $image]) }}" onsubmit="return confirm('{{ __('Remove this photo?') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="position:absolute;top:2px;right:2px;background:#fff;border:1px solid #b3261e;color:#b3261e;border-radius:50%;width:24px;height:24px;cursor:pointer;">×</button>
                        </form>
                    </figure>
                @endforeach
            </div>
        </div>
    @endif

    <details class="admin-card" @if ($usesLengthPrices) open @endif>
        <summary style="cursor:pointer;font-weight:600;">{{ __('Prices by rental length (advanced)') }}</summary>
        <p class="admin-hint">{{ __('Only if longer rentals get a different price, e.g. 1–3 days 25, 4+ days 20. Otherwise just use the price in the form above.') }}</p>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('From (:unit)', ['unit' => __($package->tierUnit())]) }}</th>
                    <th>{{ __('To (:unit)', ['unit' => __($package->tierUnit())]) }}</th>
                    <th>{{ __('Price') }} (USD)</th>
                    <th>{{ __('Price') }} (EUR)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($package->pricingTiers as $tier)
                    <tr>
                        <td>{{ $tier->min_days }}</td>
                        <td>{{ $tier->max_days ?? __('unlimited') }}</td>
                        <td><input type="number" name="price" form="tier-{{ $tier->id }}" value="{{ $tier->price }}" min="0" step="0.01" aria-label="{{ __('Price') }} (USD)" style="width:6rem;" required></td>
                        <td><input type="number" name="price_eur" form="tier-{{ $tier->id }}" value="{{ $tier->price_eur }}" min="0" step="0.01" aria-label="{{ __('Price') }} (EUR)" style="width:6rem;" required></td>
                        <td>
                            <form method="POST" id="tier-{{ $tier->id }}" action="{{ route('admin.packages.pricing-tiers.update', [$package, $tier]) }}" style="display:inline;">
                                @csrf
                                @method('PUT')
                                <button type="submit" style="background:none;border:none;color:#0f6b4c;cursor:pointer;padding:0;">{{ __('Save') }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.packages.pricing-tiers.destroy', [$package, $tier]) }}" style="display:inline;margin-left:0.5rem;" onsubmit="return confirm('{{ __('Remove this tier?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:#b3261e;cursor:pointer;padding:0;">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @error('price')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
        @error('price_eur')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror

        <form method="POST" action="{{ route('admin.packages.pricing-tiers.store', $package) }}" style="margin-top:1rem;" novalidate>
            @csrf
            <div class="admin-form-field">
                <label for="tier_min_days">{{ __('From (:unit)', ['unit' => __($package->tierUnit())]) }}</label>
                <input type="number" id="tier_min_days" name="min_days" min="1" required>
            </div>
            <div class="admin-form-field">
                <label for="tier_max_days">{{ __('To (:unit, blank = unlimited)', ['unit' => __($package->tierUnit())]) }}</label>
                <input type="number" id="tier_max_days" name="max_days" min="1">
            </div>
            <div class="admin-form-field">
                <label for="tier_price">{{ $package->tierPriceUnit() === null ? __('Total price for this tier') : __('Price per :unit for this tier', ['unit' => __($package->tierPriceUnit())]) }} (USD)</label>
                <input type="number" id="tier_price" name="price" min="0" step="0.01" required>
            </div>
            <div class="admin-form-field">
                <label for="tier_price_eur">{{ $package->tierPriceUnit() === null ? __('Total price for this tier') : __('Price per :unit for this tier', ['unit' => __($package->tierPriceUnit())]) }} (EUR)</label>
                <input type="number" id="tier_price_eur" name="price_eur" min="0" step="0.01" required>
            </div>
            <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Add tier') }}</button>
        </form>
    </details>

    <details class="admin-card" @if ($package->seasons->isNotEmpty()) open @endif>
        <summary style="cursor:pointer;font-weight:600;">{{ __('Seasonal prices (advanced)') }}</summary>
        <p class="admin-hint">{{ __('Raise or lower the price between two dates, e.g. +20% in peak season.') }}</p>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('From') }}</th>
                    <th>{{ __('To') }}</th>
                    <th>{{ __('Modifier') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($package->seasons as $season)
                    <tr>
                        <td>{{ $season->name }}</td>
                        <td>{{ $season->starts_on->toDateString() }}</td>
                        <td>{{ $season->ends_on->toDateString() }}</td>
                        <td>{{ $season->price_modifier_type === 'percent' ? $season->price_modifier_value.'%' : \Modules\Pricing\Support\Money::dual($season->price_modifier_value, $season->price_modifier_value_eur) }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.packages.seasons.destroy', [$package, $season]) }}" onsubmit="return confirm('{{ __('Remove this season?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:#b3261e;cursor:pointer;padding:0;">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <form method="POST" action="{{ route('admin.packages.seasons.store', $package) }}" style="margin-top:1rem;" novalidate>
            @csrf
            <div class="admin-form-field">
                <label for="season_name">{{ __('Name') }}</label>
                <input type="text" id="season_name" name="name" placeholder="{{ __('e.g. Peak Season') }}" required>
            </div>
            <div class="admin-form-field">
                <label for="season_starts_on">{{ __('From') }}</label>
                <input type="date" id="season_starts_on" name="starts_on" required>
            </div>
            <div class="admin-form-field">
                <label for="season_ends_on">{{ __('To') }}</label>
                <input type="date" id="season_ends_on" name="ends_on" required>
            </div>
            <div class="admin-form-field">
                <label for="price_modifier_type">{{ __('Modifier type') }}</label>
                <select id="price_modifier_type" name="price_modifier_type">
                    <option value="percent">{{ __('Percent') }}</option>
                    <option value="fixed">{{ __('Fixed amount') }}</option>
                </select>
            </div>
            <div class="admin-form-field">
                <label for="price_modifier_value">{{ __('Modifier value (percent, or USD amount)') }}</label>
                <input type="number" id="price_modifier_value" name="price_modifier_value" step="0.01" required>
            </div>
            <div class="admin-form-field">
                <label for="price_modifier_value_eur">{{ __('EUR amount (fixed amount only)') }}</label>
                <input type="number" id="price_modifier_value_eur" name="price_modifier_value_eur" step="0.01">
                @error('price_modifier_value_eur')<p class="field-error" style="color:#b3261e;">{{ $message }}</p>@enderror
            </div>
            <fieldset class="admin-form-field">
                <legend style="font-weight:600;margin-bottom:0.5rem;">{{ __('Applies on') }}</legend>
                @foreach ([0 => __('Mon'), 1 => __('Tue'), 2 => __('Wed'), 3 => __('Thu'), 4 => __('Fri'), 5 => __('Sat'), 6 => __('Sun')] as $value => $label)
                    <label style="display:inline-block;font-weight:400;margin-right:0.75rem;"><input type="checkbox" name="weekdays[]" value="{{ $value }}" checked> {{ $label }}</label>
                @endforeach
            </fieldset>
            <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Add season') }}</button>
        </form>
    </details>

</x-admin::layouts.app>
