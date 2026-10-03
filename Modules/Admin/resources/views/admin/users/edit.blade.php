<x-admin::layouts.app :title="__('Edit user')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" novalidate>
            @csrf
            @method('PUT')

            <div class="admin-form-field">
                <label for="name">{{ __('Name') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
            </div>

            <div class="admin-form-field">
                <label for="email">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
            </div>

            <div class="admin-form-field">
                <label for="password">{{ __('New password') }} <span style="font-weight:400;color:#5b6b64;">({{ __('leave blank to keep current') }})</span></label>
                <input type="password" id="password" name="password" minlength="12" autocomplete="new-password">
            </div>

            <div class="admin-form-field">
                <label for="password_confirmation">{{ __('Confirm new password') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" minlength="12" autocomplete="new-password">
            </div>

            <div class="admin-form-field">
                <label for="role">{{ __('Role') }}</label>
                <select id="role" name="role" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected(old('role', $user->roles->first()?->name) === $role->name)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="admin-form-field">
                <label>
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                    {{ __('Active') }}
                </label>
            </div>

            <button type="submit" class="admin-btn">{{ __('Save changes') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
