<x-admin::layouts.app :title="__('Backups')">
    <div class="admin-card">
        <h2>{{ __('Download a database backup') }}</h2>
        <p style="color:#5b6b64;">{{ __('Saves all bookings, customers, packages, prices, pages and settings to one file on your computer. Nothing is kept on the server.') }}</p>

        <div class="admin-alert admin-alert--warn">
            <ul style="margin:0;padding-left:1.1rem;">
                <li>{{ __('Keep this file private. It holds every customer\'s details and every staff login.') }}</li>
                <li>{{ __('Uploaded photos and logos are not in this file. They are stored separately on the server.') }}</li>
                <li>{{ __('Passport numbers are encrypted. Restoring them also needs the APP_KEY from the server\'s .env file, so keep a safe copy of it.') }}</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('admin.backups.store') }}" novalidate>
            @csrf
            <div class="admin-form-field">
                <label for="password">{{ __('Your password') }}</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="admin-btn">{{ __('Download backup') }}</button>
        </form>
    </div>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Taken by') }}</th>
                    <th>{{ __('Size') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($backups as $backup)
                    <tr>
                        <td>{{ $backup->created_at->timezone(config('booking.business_timezone'))->format('d M Y, H:i') }}</td>
                        <td>{{ $backup->causer?->name ?? '—' }}</td>
                        <td>{{ number_format((int) $backup->getExtraProperty('bytes') / 1048576, 2) }} MB</td>
                    </tr>
                @empty
                    <tr><td colspan="3">{{ __('No backups taken yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin::layouts.app>
