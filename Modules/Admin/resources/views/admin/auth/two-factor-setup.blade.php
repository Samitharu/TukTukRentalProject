<x-admin::layouts.guest :title="__('Set up two-factor authentication')">
    <div class="admin-card">
        <p>{{ __('Your role requires two-factor authentication. Scan this QR code with an authenticator app (Google Authenticator, Authy, 1Password, etc.), then enter the 6-digit code it shows you.') }}</p>

        <div style="margin:1rem 0;">{!! $qrSvg !!}</div>

        <p>{{ __('Or enter this code manually:') }} <code>{{ $secret }}</code></p>

        <form method="POST" action="{{ route('admin.2fa.confirm') }}" novalidate>
            @csrf
            <div class="admin-form-field">
                <label for="code">{{ __('Authentication code') }}</label>
                <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" required autofocus>
            </div>
            <button type="submit" class="admin-btn">{{ __('Confirm') }}</button>
        </form>
    </div>
</x-admin::layouts.guest>
