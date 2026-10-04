<x-core::layouts.public :title="__('core::front.booking_details_title').' · '.config('app.name')">
    <section class="section booking-flow">
        <div class="container" style="max-width:34rem;">
            @include('booking::front.steps._indicator', ['current' => 'details'])

            <h1>{{ __('core::front.booking_details_title') }}</h1>
            <p class="text-muted">{{ __($isStay ? 'core::front.booking_details_intro_stay' : 'core::front.booking_details_intro') }}</p>

            <form class="booking-panel" method="POST" action="{{ route('booking.details.store') }}" novalidate>
                @csrf

                <div class="grid grid--2">
                    <div class="field">
                        <label for="first_name">{{ __('core::front.booking_first_name') }}</label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $state['first_name'] ?? '') }}" required>
                        @error('first_name')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="last_name">{{ __('core::front.booking_last_name') }}</label>
                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $state['last_name'] ?? '') }}" required>
                        @error('last_name')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="field">
                    <label for="email">{{ __('core::front.booking_email') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $state['email'] ?? '') }}" required>
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="phone">{{ __('core::front.booking_phone') }}</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $state['phone'] ?? '') }}" required>
                    @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid--2">
                    <div class="field">
                        <label for="nationality">{{ __('core::front.booking_nationality') }}</label>
                        <select id="nationality" name="nationality" required>
                            <option value="">&nbsp;</option>
                            @foreach ($countries as $code => $name)
                                <option value="{{ $code }}" @selected(old('nationality', $state['nationality'] ?? '') === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('nationality')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="passport_number">{{ __('core::front.booking_passport_number') }}</label>
                        <input type="text" id="passport_number" name="passport_number" value="{{ old('passport_number', $state['passport_number'] ?? '') }}" required>
                        @error('passport_number')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                @if (! $isStay)
                <div class="field">
                    <label class="check">
                        <input type="hidden" name="has_valid_licence" value="0">
                        <input type="checkbox" name="has_valid_licence" value="1" @checked(old('has_valid_licence', $state['has_valid_licence'] ?? false))>
                        {{ __('core::front.booking_has_valid_licence') }}
                    </label>
                    @error('has_valid_licence')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label class="check">
                        <input type="hidden" name="has_international_permit" value="0">
                        <input type="checkbox" name="has_international_permit" value="1" @checked(old('has_international_permit', $state['has_international_permit'] ?? false))>
                        {{ __('core::front.booking_has_international_permit') }}
                    </label>
                </div>
                @endif

                <div class="field">
                    <label for="special_requests">{{ __('core::front.booking_special_requests') }}</label>
                    <textarea id="special_requests" name="special_requests">{{ old('special_requests', $state['special_requests'] ?? '') }}</textarea>
                </div>

                <div class="field">
                    <label class="check">
                        <input type="hidden" name="marketing_opt_in" value="0">
                        <input type="checkbox" name="marketing_opt_in" value="1" @checked(old('marketing_opt_in', $state['marketing_opt_in'] ?? false))>
                        {{ __('core::front.booking_marketing_opt_in') }}
                    </label>
                </div>

                <div style="display:flex;gap:var(--space-3);margin-top:var(--space-4);">
                    <a href="{{ route('booking.addons') }}" class="btn btn--secondary">{{ __('core::front.booking_back') }}</a>
                    <button type="submit" class="btn btn--primary" style="flex:1;">{{ __('core::front.booking_continue') }}</button>
                </div>
            </form>
        </div>
    </section>
</x-core::layouts.public>
