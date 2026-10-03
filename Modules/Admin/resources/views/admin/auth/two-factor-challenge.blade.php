<x-admin::layouts.guest :title="__('Two-factor authentication')">
    <div class="admin-card">
        <p>{{ __('Enter the 6-digit code from your authenticator app, or one of your recovery codes.') }}</p>

        <form method="POST" action="{{ route('admin.2fa.verify') }}" novalidate>
            @csrf
            <div class="admin-form-field">
                <label for="code">{{ __('Code') }}</label>
                <input type="text" id="code" name="code" autocomplete="one-time-code" required autofocus>
            </div>
            <button type="submit" class="admin-btn">{{ __('Verify') }}</button>
        </form>
    </div>
</x-admin::layouts.guest>
