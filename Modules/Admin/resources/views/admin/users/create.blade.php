<x-admin::layouts.app :title="__('Add user')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.users.store') }}" novalidate>
            @csrf

            <div class="admin-form-field">
                <label for="name">{{ __('Name') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required>
            </div>

            <div class="admin-form-field">
                <label for="email">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required>
            </div>

            <div class="admin-form-field">
                <label for="password">{{ __('Password') }}</label>
                <input type="password" id="password" name="password" required minlength="12" autocomplete="new-password">
            </div>

            <div class="admin-form-field">
                <label for="password_confirmation">{{ __('Confirm password') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required minlength="12" autocomplete="new-password">
            </div>

            <div class="admin-form-field">
                <label for="role">{{ __('Role') }}</label>
                <select id="role" name="role" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected(old('role') === $role->name)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="admin-btn">{{ __('Create user') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
