<x-admin::layouts.app :title="__('Message from :name', ['name' => $contactMessage->name])">
    <div class="admin-card">
        <p><strong>{{ __('From') }}:</strong> {{ $contactMessage->name }} (<a href="mailto:{{ $contactMessage->email }}">{{ $contactMessage->email }}</a>)</p>
        @if ($contactMessage->subject)
            <p><strong>{{ __('Subject') }}:</strong> {{ $contactMessage->subject }}</p>
        @endif
        <p><strong>{{ __('Received') }}:</strong> {{ $contactMessage->created_at?->format('d M Y, H:i') }}</p>
        @if ($contactMessage->locale)
            <p><strong>{{ __('Website language') }}:</strong> {{ strtoupper($contactMessage->locale) }}</p>
        @endif

        <div class="admin-message__body">{{ $contactMessage->message }}</div>

        <p style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;margin-top:1.25rem;">
            <a href="mailto:{{ $contactMessage->email }}?subject={{ rawurlencode(__('Re: :subject', ['subject' => $contactMessage->subject ?: config('app.name')])) }}" class="admin-btn">{{ __('Reply by email') }}</a>
            <a href="{{ route('admin.messages.index') }}" class="admin-btn admin-btn--secondary">{{ __('Back to messages') }}</a>
        </p>

        @can('cms.manage')
            <div style="display:flex;flex-wrap:wrap;gap:0.75rem;">
                <form method="POST" action="{{ route('admin.messages.unread', $contactMessage) }}">
                    @csrf
                    <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Mark as unread') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.messages.destroy', $contactMessage) }}" data-confirm="{{ __('Remove this message?') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="admin-btn admin-btn--danger">{{ __('Remove') }}</button>
                </form>
            </div>
        @endcan
    </div>
</x-admin::layouts.app>
