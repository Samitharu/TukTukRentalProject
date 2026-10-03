@php
    $steps = [
        'dates' => __('core::front.booking_step_dates'),
        'package' => __('core::front.booking_step_package'),
        'addons' => __('core::front.booking_step_addons'),
        'details' => __('core::front.booking_step_details'),
        'review' => __('core::front.booking_step_review'),
    ];
    $order = array_keys($steps);
    $currentIndex = array_search($current ?? 'dates', $order, true);
@endphp
<ol class="steps" aria-label="{{ __('core::front.booking_step_review') }}">
    @foreach ($steps as $key => $label)
        @php $isDone = array_search($key, $order, true) < $currentIndex; @endphp
        <li
            @if ($key === ($current ?? 'dates')) aria-current="step" @endif
            data-done="{{ $isDone ? 'true' : 'false' }}"
        >
            <span class="steps__num" aria-hidden="true">
                @if ($isDone)
                    <svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @else
                    {{ $loop->iteration }}
                @endif
            </span>
            <span class="steps__label">{{ $label }}</span>
        </li>
    @endforeach
</ol>
