@php
    // DomPDF renders a limited CSS subset (no flex/grid, no CSS variables),
    // so this template sticks to tables and plain hex colours. DejaVu Sans
    // ships with DomPDF and covers accented/Cyrillic names.
    $p = $booking->price_breakdown ?? [];
    $currency = $booking->currency_code;
    $money = fn ($value) => number_format((float) $value, 2).' '.$currency;
    $balance = max(0, (float) $booking->total_amount - (float) $booking->amount_paid);
    $isStay = $booking->isStay();
    // Rental days, or nights for a stay (end_at is its last night).
    $days = $p['days'] ?? $booking->lengthInDays();
    $pickup = $booking->pickup_type === 'delivery'
        ? __('core::front.booking_pickup_delivery').($booking->deliveryZone ? ' — '.$booking->deliveryZone->name : '')
        : __('core::front.booking_pickup_office').($booking->businessLocation ? ' — '.$booking->businessLocation->name : '');
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
        h2 { font-size: 12px; color: #0c5a3f; text-transform: uppercase; letter-spacing: 1px; margin: 18px 0 6px; padding-bottom: 4px; border-bottom: 2px solid #4fb890; }
        .kv td { padding: 4px 0; vertical-align: top; }
        .kv td.k { width: 34%; color: #5b6b64; }
        .lines td { padding: 6px 0; border-bottom: 1px solid #e7efeb; }
        .lines td.amt { text-align: right; white-space: nowrap; }
        .total td { padding: 9px 0; font-size: 14px; font-weight: bold; border-top: 2px solid #0c5a3f; border-bottom: 0; }
        .balance td { padding: 8px 10px; background: #fff4de; font-weight: bold; color: #7a4a00; }
        .footer { margin-top: 26px; padding-top: 12px; border-top: 1px solid #dfe6e2; font-size: 9.5px; color: #5b6b64; text-align: center; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 8px; background: #0f6b4c; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: 1px; }
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
                        {{ config('core.business.address') }}<br>
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
                <td>
                    <div class="muted">{{ __('core::front.booking_confirmation_reference') }}</div>
                    <div class="ref">{{ $booking->reference }}</div>
                </td>
                <td style="text-align:right;vertical-align:middle;width:112px;">
                    <div><span class="muted">{{ __('core::front.receipt_status') }}</span></div>
                    <span class="status">{{ __('core::front.booking_status_'.$booking->status) }}</span>
                    <div style="margin-top:8px;">
                        <img src="data:image/svg+xml;base64,{{ $statusQrCode }}" alt="" width="72" height="72">
                    </div>
                    <div class="muted" style="font-size:8px;">{{ __('core::front.booking_status_scan_prompt') }}</div>
                </td>
            </tr>
        </table>
    </div>

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
                @if ($isStay)
                    <h2>{{ __('core::front.receipt_stay') }}</h2>
                    <table class="kv">
                        <tr><td class="k">{{ __('core::front.booking_check_in') }}</td><td>{{ $booking->start_at->format('d M Y') }}</td></tr>
                        <tr><td class="k">{{ __('core::front.booking_check_out') }}</td><td>{{ $booking->checkOutDate()->format('d M Y') }}</td></tr>
                        <tr><td class="k">{{ __('core::front.receipt_nights') }}</td><td>{{ $days }}</td></tr>
                        @if ($booking->package)
                            <tr><td class="k">{{ __('core::front.booking_review_package') }}</td><td>{{ $booking->package->name }}</td></tr>
                        @endif
                        <tr><td class="k">{{ __('core::front.receipt_unit') }}</td><td>{{ $booking->vehicle?->name }}</td></tr>
                        @if ($booking->vehicle?->address || $booking->vehicle?->hasCoordinates())
                            <tr><td class="k">{{ __('core::front.receipt_location') }}</td><td>{{ $booking->vehicle->address }}@if ($booking->vehicle->hasCoordinates())<br><span class="muted">{{ $booking->vehicle->lat }}, {{ $booking->vehicle->lng }}</span>@endif</td></tr>
                        @endif
                    </table>
                @else
                    <h2>{{ __('core::front.receipt_rental') }}</h2>
                    <table class="kv">
                        <tr><td class="k">{{ __('core::front.booking_review_dates') }}</td><td>{{ $booking->rentalPeriod() }}</td></tr>
                        @unless ($booking->isHourly())
                            <tr><td class="k">{{ __('core::front.receipt_days') }}</td><td>{{ $days }}</td></tr>
                        @endunless
                        @if ($booking->included_km !== null)
                            <tr><td class="k">{{ __('core::front.receipt_km_included') }}</td><td>{{ number_format($booking->included_km) }} km</td></tr>
                        @endif
                        @if ($booking->package)
                            <tr><td class="k">{{ __('core::front.booking_review_package') }}</td><td>{{ $booking->package->name }}</td></tr>
                        @endif
                        <tr><td class="k">{{ __('core::front.receipt_vehicle') }}</td><td>{{ $booking->vehicle?->name }} ({{ $booking->vehicle?->plate_no }})</td></tr>
                        <tr><td class="k">{{ __('core::front.receipt_pickup') }}</td><td>{{ $pickup }}</td></tr>
                    </table>
                @endif
            </td>
        </tr>
    </table>

    <h2>{{ __('core::front.receipt_charges') }}</h2>
    <table class="lines">
        <tr><td>{{ __($isStay ? 'core::front.price_accommodation' : 'core::front.price_base_amount') }}</td><td class="amt">{{ $money($p['base_amount'] ?? $booking->total_amount) }}</td></tr>

        @if ((float) ($p['seasonal_adjustment'] ?? 0) != 0)
            <tr><td>{{ __('core::front.price_seasonal_adjustment') }}</td><td class="amt">{{ $money($p['seasonal_adjustment']) }}</td></tr>
        @endif

        @foreach ($p['addon_lines'] ?? [] as $line)
            <tr>
                <td>{{ is_array($line['name'] ?? null) ? (reset($line['name']) ?: '') : ($line['name'] ?? '') }} × {{ $line['quantity'] ?? 1 }}</td>
                <td class="amt">{{ $money($line['amount'] ?? 0) }}</td>
            </tr>
        @endforeach

        @if ((float) ($p['delivery_fee'] ?? 0) > 0)
            <tr><td>{{ __('core::front.price_delivery_fee') }}</td><td class="amt">{{ $money($p['delivery_fee']) }}</td></tr>
        @endif

        @if ((float) ($p['coupon_discount'] ?? 0) > 0)
            <tr><td>{{ __('core::front.price_coupon_discount') }} ({{ $p['coupon_code'] ?? '' }})</td><td class="amt">-{{ $money($p['coupon_discount']) }}</td></tr>
        @endif

        <tr class="total"><td>{{ __('core::front.price_total') }}</td><td class="amt">{{ $money($booking->total_amount) }}</td></tr>
    </table>

    <table class="kv" style="margin-top:8px;">
        @if ((float) $booking->deposit_amount > 0)
            <tr><td class="k">{{ __('core::front.receipt_deposit') }}</td><td style="text-align:right;">{{ $money($booking->deposit_amount) }}</td></tr>
        @endif
        <tr><td class="k">{{ __('core::front.receipt_amount_paid') }}</td><td style="text-align:right;">{{ $money($booking->amount_paid) }}</td></tr>
    </table>

    <table class="balance" style="margin-top:8px;">
        <tr><td>{{ __($isStay ? 'core::front.receipt_balance_due_stay' : 'core::front.receipt_balance_due') }}</td><td style="text-align:right;">{{ $money($balance) }}</td></tr>
    </table>

    <div class="footer">
        {{ __($isStay ? 'core::front.receipt_footer_stay' : 'core::front.receipt_footer') }}<br>
        {{ config('app.name') }} · {{ config('core.business.phone') }} · {{ config('core.business.email') }}
    </div>
</body>
</html>
