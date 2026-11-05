<x-admin::layouts.app :title="__('Messages')">
    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead><tr><th>{{ __('From') }}</th><th>{{ __('Message') }}</th><th>{{ __('Received') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($contactMessages as $contactMessage)
                    <tr @class(['admin-message--unread' => ! $contactMessage->is_read])>
                        <td>
                            @unless ($contactMessage->is_read)
                                <span class="admin-badge">{{ __('New') }}</span>
                            @endunless
                            {{ $contactMessage->name }}<br>
                            <small>{{ $contactMessage->email }}</small>
                        </td>
                        <td>
                            @if ($contactMessage->subject)
                                {{ $contactMessage->subject }}<br>
                            @endif
                            <small>{{ \Illuminate\Support\Str::limit($contactMessage->message, 90) }}</small>
                        </td>
                        <td>{{ $contactMessage->created_at?->format('d M Y, H:i') }}</td>
                        <td><a href="{{ route('admin.messages.show', $contactMessage) }}">{{ __('Read') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4">{{ __('No messages yet. Messages sent from the Contact page appear here.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $contactMessages->links() }}
</x-admin::layouts.app>
