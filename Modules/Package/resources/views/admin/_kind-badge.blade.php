{{-- Booking-style badge: Rental / Stay / Activity. --}}
<span class="admin-badge admin-badge--{{ $kind }}">{{ match ($kind) {
    \Modules\Package\Models\Package::KIND_STAY => __('Stay'),
    \Modules\Package\Models\Package::KIND_ACTIVITY => __('Activity'),
    default => __('Rental'),
} }}</span>
