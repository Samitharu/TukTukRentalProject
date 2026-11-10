{{-- After booking a rental that needs it: send the driving licence by WhatsApp or email (for the Sri Lankan permit). --}}
@php $licenceBooking = $booking->checkoutBookings()->first(fn ($item) => $item->licence_documents_required && $item->status !== $item::STATUS_CANCELLED); @endphp
@if ($licenceBooking?->awaitsLicenceDocuments())
    <div class="alert alert--warning licence-next" role="note">
        <h2 style="font-size:var(--font-size-lg);margin:0 0 var(--space-2);">{{ __('core::front.licence_next_title') }}</h2>
        <p style="margin:0 0 var(--space-3);">{{ __('core::front.licence_next_text', ['reference' => $licenceBooking->reference]) }}</p>
        <div style="display:flex;flex-wrap:wrap;gap:var(--space-2);">
            <a href="{{ $licenceBooking->licenceWhatsAppUrl() }}" class="btn btn--primary" target="_blank" rel="noopener">{{ __('core::front.licence_send_whatsapp') }}</a>
            <a href="{{ $licenceBooking->licenceEmailUrl() }}" class="btn btn--secondary">{{ __('core::front.licence_send_email') }}</a>
        </div>
    </div>
@elseif ($licenceBooking?->licence_documents_received_at)
    <div class="alert alert--success" role="status">{{ __('core::front.licence_received') }}</div>
@endif
