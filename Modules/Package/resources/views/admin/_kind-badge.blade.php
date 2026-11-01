{{-- Booking-style badge: Rental / Stay / Activity / Airport pickup. --}}
<span class="admin-badge admin-badge--{{ $kind }}">{{ match ($kind) {
    \Modules\Package\Models\Package::KIND_STAY => __('Stay'),
    \Modules\Package\Models\Package::KIND_ACTIVITY => __('Activity'),
    \Modules\Package\Models\Package::KIND_TRANSFER => __('Airport pickup'),
    default => __('Rental'),
} }}</span>
