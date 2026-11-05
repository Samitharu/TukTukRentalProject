@php
    // DomPDF renders a limited CSS subset (no flex/grid, no CSS variables),
    // so this template sticks to tables and plain hex colours. DejaVu Sans
    // ships with DomPDF and covers accented/Cyrillic names.
    //
    // Packages booked in the same checkout share one receipt, whichever
    // booking's reference it was opened from.
    $bookings = $booking->checkoutBookings();
    $isCombined = $bookings->count() > 1;
    $money = fn ($usd, $eur = null) => \Modules\Pricing\Support\Money::dual($usd, $eur);
    $isStay = $booking->isStay();
    $owed = $bookings->reject(fn ($item) => $item->status === $item::STATUS_CANCELLED);
    $deposit = (float) ($isCombined ? $owed->sum('deposit_amount') : $booking->deposit_amount);
    $paid = (float) ($isCombined ? $owed->sum('amount_paid') : $booking->amount_paid);
    $footers = $bookings->map(fn ($item) => __(match (true) {
        $item->isStay() => 'core::front.receipt_footer_stay',
        $item->isTransfer() => 'core::front.receipt_footer_transfer',
        $item->isActivity() => 'core::front.receipt_footer_activity',
        default => 'core::front.receipt_footer',
    }))->unique();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('core::front.receipt_title') }} {{ $booking->reference }}</title>
    <style>
        @page { margin: 28px 36px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 11px; color: #16231d; margin: 0; }
        .band { background: #0c5a3f; color: #fff; padding: 18px 22px; }
        .band td { color: #fff; vertical-align: top; }
        .brand { font-size: 20px; font-weight: bold; }
        .tagline { font-size: 9px; letter-spacing: 2px; color: #c0e6d7; text-transform: uppercase; }
        .doc-title { font-size: 16px; font-weight: bold; text-align: right; }
        .muted { color: #5b6b64; }
        .band .muted { color: #c0e6d7; }
        table { width: 100%; border-collapse: collapse; }
        .ref-box { margin: 18px 0; border: 1px solid #c0e6d7; background: #e6f5ef; padding: 12px 16px; }
        .ref { font-size: 18px; font-weight: bold; letter-spacing: 1.5px; color: #094a33; }
        .refs .ref { font-size: 14px; margin-top: 3px; }
        h2 { font-size: 12px; color: #0c5a3f; text-transform: uppercase; letter-spacing: 1px; margin: 18px 0 6px; padding-bottom: 4px; border-bottom: 2px solid #4fb890; }
        .kv td { padding: 4px 0; vertical-align: top; }
        .kv td.k { width: 34%; color: #5b6b64; }
        .lines td { padding: 6px 0; border-bottom: 1px solid #e7efeb; }
        .lines td.amt { text-align: right; white-space: nowrap; }
        .total td { padding: 9px 0; font-size: 14px; font-weight: bold; border-top: 2px solid #0c5a3f; border-bottom: 0; }
        .balance td { padding: 8px 10px; background: #fff4de; font-weight: bold; color: #7a4a00; }
        .footer { margin-top: 26px; padding-top: 12px; border-top: 1px solid #dfe6e2; font-size: 9.5px; color: #5b6b64; text-align: center; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 8px; background: #0f6b4c; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: 1px; }
        .booking-part { margin-top: 12px; padding-top: 8px; border-top: 3px solid #0c5a3f; page-break-inside: avoid; }
        .booking-part__title { font-size: 13px; font-weight: bold; color: #094a33; }
        .booking-part h2 { margin: 9px 0 3px; padding-bottom: 2px; border-bottom-width: 1px; font-size: 10px; }
        .booking-part .kv td { padding: 2px 0; }
        .booking-part .lines td { padding: 3px 0; }
        .booking-part .total td { padding: 5px 0 0; font-size: 12px; }
        .grand { margin-top: 18px; page-break-inside: avoid; }
        .grand-total td { padding: 10px 12px; background: #0c5a3f; color: #fff; font-size: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="band">
        <table>
            <tr>
                <td>
                    <div class="brand">{{ config('app.name') }}</div>
                    @if (config('core.business.tagline'))
                        <div class="tagline">{{ config('core.business.tagline') }}</div>
                    @endif
                    <div class="muted" style="margin-top:6px;">
                        @if ($booking::businessAddress())
                            {{ $booking::businessAddress() }}<br>
                        @endif
                        {{ config('core.business.phone') }} · {{ config('core.business.email') }}
                    </div>
                </td>
                <td style="text-align:right;">
                    <div class="doc-title">{{ __('core::front.receipt_title') }}</div>
                    <div class="muted" style="margin-top:6px;">{{ __('core::front.receipt_issued') }}: {{ now()->format('d M Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="ref-box">
        <table>
            <tr>
                <td @class(['refs' => $isCombined])>
                    <div class="muted">{{ __($isCombined ? 'core::front.receipt_references' : 'core::front.booking_confirmation_reference') }}</div>
                    @foreach ($bookings as $item)
                        <div class="ref">{{ $item->reference }}</div>
                    @endforeach
                </td>
                <td style="text-align:right;vertical-align:middle;width:112px;">
                    @unless ($isCombined)
                        <div><span class="muted">{{ __('core::front.receipt_status') }}</span></div>
                        <span class="status">{{ __('core::front.booking_status_'.$booking->status) }}</span>
                    @endunless
                    <div style="margin-top:8px;">
                        <img src="data:image/svg+xml;base64,{{ $statusQrCode }}" alt="" width="72" height="72">
                    </div>
                    <div class="muted" style="font-size:8px;">{{ __('core::front.booking_status_scan_prompt') }}</div>
                </td>
            </tr>
        </table>
    </div>

    @if ($isCombined)
        <h2>{{ __('core::front.receipt_customer') }}</h2>
        <table class="kv">
            <tr><td>{{ $booking->customer?->full_name }}</td></tr>
            <tr><td class="muted">{{ $booking->customer?->email }}</td></tr>
            @if ($booking->customer?->phone)
                <tr><td class="muted">{{ $booking->customer->phone }}</td></tr>
            @endif
        </table>

        @foreach ($bookings as $item)
            <div class="booking-part">
                <table>
                    <tr>
                        <td class="booking-part__title">{{ $item->package?->name }}</td>
                        <td style="text-align:right;">
                            <span class="muted">{{ $item->reference }}</span>
                            <span class="status">{{ __('core::front.booking_status_'.$item->status) }}</span>
                        </td>
                    </tr>
                </table>
                @include('booking::pdf._receipt-details', ['booking' => $item])
                @include('booking::pdf._receipt-charges', ['booking' => $item])
            </div>
        @endforeach

        <div class="grand">
            <table class="grand-total">
                <tr><td>{{ __('core::front.offer_total_all') }}</td><td style="text-align:right;">{{ $booking->displayTotalWithLinked() }}</td></tr>
            </table>
    @else
        <table>
            <tr>
                <td style="width:48%;vertical-align:top;">
                    <h2>{{ __('core::front.receipt_customer') }}</h2>
                    <table class="kv">
                        <tr><td>{{ $booking->customer?->full_name }}</td></tr>
                        <tr><td class="muted">{{ $booking->customer?->email }}</td></tr>
                        @if ($booking->customer?->phone)
                            <tr><td class="muted">{{ $booking->customer->phone }}</td></tr>
                        @endif
                    </table>
                </td>
                <td style="width:4%;"></td>
                <td style="width:48%;vertical-align:top;">
                    @include('booking::pdf._receipt-details', ['booking' => $booking])
                </td>
            </tr>
        </table>

        @include('booking::pdf._receipt-charges', ['booking' => $booking])
        <div>
    @endif

        <table class="kv" style="margin-top:8px;">
            @if ($deposit > 0)
                <tr><td class="k">{{ __('core::front.receipt_deposit') }}</td><td style="text-align:right;">{{ $money($deposit) }}</td></tr>
            @endif
            <tr><td class="k">{{ __('core::front.receipt_amount_paid') }}</td><td style="text-align:right;">{{ $money($paid) }}</td></tr>
        </table>

        <table class="balance" style="margin-top:8px;">
            @if ($isCombined)
                <tr><td>{{ __('core::front.receipt_balance_due_all') }}</td><td style="text-align:right;">{{ $booking->displayBalanceWithLinked() }}</td></tr>
            @else
                <tr><td>{{ __($isStay ? 'core::front.receipt_balance_due_stay' : ($booking->isActivity() ? 'core::front.receipt_balance_due_activity' : 'core::front.receipt_balance_due')) }}</td><td style="text-align:right;">{{ $booking->displayBalance() }}</td></tr>
            @endif
        </table>
    </div>

    <div class="footer">
        @foreach ($footers as $footer)
            {{ $footer }}<br>
        @endforeach
        {{ config('app.name') }} · {{ config('core.business.phone') }} · {{ config('core.business.email') }}
    </div>
</body>
</html>
