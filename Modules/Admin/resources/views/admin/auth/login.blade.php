<x-admin::layouts.guest :title="__('Sign in')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.login.attempt') }}" novalidate>
            @csrf

            <div class="admin-form-field">
                <label for="email">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>

            <div class="admin-form-field">
                <label for="password">{{ __('Password') }}</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="admin-btn">{{ __('Sign in') }}</button>
        </form>
    </div>
</x-admin::layouts.guest>
