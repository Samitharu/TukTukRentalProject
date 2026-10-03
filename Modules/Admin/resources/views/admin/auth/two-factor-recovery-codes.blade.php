<x-admin::layouts.guest :title="__('Save your recovery codes')">
    <div class="admin-card">
        <p><strong>{{ __('Store these recovery codes somewhere safe. Each one can be used once to sign in if you lose access to your authenticator app. They will not be shown again.') }}</strong></p>

        <div class="admin-recovery-codes">
            @foreach ($codes as $code)
                <div>{{ $code }}</div>
            @endforeach
        </div>

        <p style="margin-top:1rem;">
            <a href="{{ route('admin.dashboard') }}" class="admin-btn">{{ __('I have saved these codes — continue') }}</a>
        </p>
    </div>
</x-admin::layouts.guest>
