{{-- Markdown mail: keep lines flush-left — 4+ spaces of indentation turns a line into a code block. --}}
<x-mail::message>
# {{ __('New website message') }}

<x-mail::table>
| | |
|:--|:--|
| **{{ __('From') }}** | {{ $md($contactMessage->name) }} |
| **{{ __('Email') }}** | {{ $md($contactMessage->email) }} |
@if ($contactMessage->subject)
| **{{ __('Subject') }}** | {{ $md($contactMessage->subject) }} |
@endif
</x-mail::table>

@foreach ($lines as $line)
{{ $md($line) }}

@endforeach
<x-mail::button :url="$adminUrl">
{{ __('Open in control panel') }}
</x-mail::button>

{{ __('Reply to this email to answer the customer directly.') }}
</x-mail::message>
