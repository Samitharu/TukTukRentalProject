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

    <div class="admin-card">
        <h2>{{ __('Pricing tiers') }}</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __($package->isStay() ? 'From (nights)' : 'From (days)') }}</th>
                    <th>{{ __($package->isStay() ? 'To (nights)' : 'To (days)') }}</th>
                    <th>{{ __('Price') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($package->pricingTiers as $tier)
                    <tr>
                        <td>{{ $tier->min_days }}</td>
                        <td>{{ $tier->max_days ?? __('unlimited') }}</td>
                        <td>{{ $tier->price }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.packages.pricing-tiers.destroy', [$package, $tier]) }}" onsubmit="return confirm('{{ __('Remove this tier?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:#b3261e;cursor:pointer;padding:0;">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <form method="POST" action="{{ route('admin.packages.pricing-tiers.store', $package) }}" style="margin-top:1rem;" novalidate>
            @csrf
            <div class="admin-form-field">
                <label for="tier_min_days">{{ __($package->isStay() ? 'From (nights)' : 'From (days)') }}</label>
                <input type="number" id="tier_min_days" name="min_days" min="1" required>
            </div>
            <div class="admin-form-field">
                <label for="tier_max_days">{{ __($package->isStay() ? 'To (nights, blank = unlimited)' : 'To (days, blank = unlimited)') }}</label>
                <input type="number" id="tier_max_days" name="max_days" min="1">
            </div>
            <div class="admin-form-field">
                <label for="tier_price">{{ __('Price for this tier') }}</label>
                <input type="number" id="tier_price" name="price" min="0" step="0.01" required>
            </div>
            <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Add tier') }}</button>
        </form>
    </div>

    <div class="admin-card">
        <h2>{{ __('Seasonal overrides') }}</h2>
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
                        <td>{{ $season->price_modifier_type === 'percent' ? $season->price_modifier_value.'%' : $season->price_modifier_value }}</td>
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
                <label for="price_modifier_value">{{ __('Modifier value') }}</label>
                <input type="number" id="price_modifier_value" name="price_modifier_value" step="0.01" required>
            </div>
            <fieldset class="admin-form-field">
                <legend style="font-weight:600;margin-bottom:0.5rem;">{{ __('Applies on') }}</legend>
                @foreach ([0 => __('Mon'), 1 => __('Tue'), 2 => __('Wed'), 3 => __('Thu'), 4 => __('Fri'), 5 => __('Sat'), 6 => __('Sun')] as $value => $label)
                    <label style="display:inline-block;font-weight:400;margin-right:0.75rem;"><input type="checkbox" name="weekdays[]" value="{{ $value }}" checked> {{ $label }}</label>
                @endforeach
            </fieldset>
            <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Add season') }}</button>
        </form>
    </div>

    <div class="admin-card">
        <h2>{{ __('Add-ons') }}</h2>
        <form method="POST" action="{{ route('admin.packages.addons.update', $package) }}" novalidate>
            @csrf
            @method('PUT')
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>{{ __('Offered') }}</th>
                        <th>{{ __('Included in price') }}</th>
                        <th>{{ __('Add-on') }}</th>
                        <th>{{ __('Price') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($addons as $addon)
                        <tr>
                            <td><input type="checkbox" name="offered_addon_ids[]" value="{{ $addon->id }}" @checked(in_array($addon->id, $offeredAddonIds, true))></td>
                            <td><input type="checkbox" name="included_addon_ids[]" value="{{ $addon->id }}" @checked(in_array($addon->id, $includedAddonIds, true))></td>
                            <td>{{ $addon->name }}</td>
                            <td>{{ $addon->price }} ({{ $addon->pricing_unit }})</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Save add-ons') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
