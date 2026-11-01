@props(['name', 'label', 'value' => [], 'textarea' => false, 'required' => false, 'compact' => false])
@php
    $locales = \Modules\Localization\Models\Locale::activeCached();
    $defaultCode = \Modules\Localization\Models\Locale::defaultCode();
    // compact: only the default language up front, the others folded away.
    $groups = $compact
        ? [$locales->where('code', $defaultCode), $locales->where('code', '!=', $defaultCode)]
        : [$locales, collect()];
@endphp
<fieldset class="admin-form-field">
    <legend style="font-weight:600;margin-bottom:0.5rem;">{{ $label }}</legend>
    @foreach ($groups as $index => $group)
        @continue($group->isEmpty())
        @if ($index === 1)
            <details style="margin-top:0.25rem;">
                <summary style="cursor:pointer;color:#5b6b64;font-size:0.85rem;">{{ __('Other languages (optional)') }}</summary>
        @endif
        @foreach ($group as $locale)
            @php $fieldId = "{$name}_{$locale->code}"; @endphp
            <div style="margin-bottom:0.5rem;">
                @unless ($compact && $locale->code === $defaultCode)
                    <label for="{{ $fieldId }}" style="font-weight:400;font-size:0.85rem;color:#5b6b64;">
                        {{ $locale->native_name }} ({{ strtoupper($locale->code) }})
                        @if ($required && $locale->code === $defaultCode)
                            <span style="color:#b3261e;">*</span>
                        @endif
                    </label>
                @endunless
                @if ($textarea)
                    <textarea
                        id="{{ $fieldId }}"
                        name="{{ $name }}[{{ $locale->code }}]"
                        rows="3"
                        @if ($compact && $locale->code === $defaultCode) aria-label="{{ $label }}" @endif
                        @if ($required && $locale->code === $defaultCode) required @endif
                    >{{ old("{$name}.{$locale->code}", $value[$locale->code] ?? '') }}</textarea>
                @else
                    <input
                        type="text"
                        id="{{ $fieldId }}"
                        name="{{ $name }}[{{ $locale->code }}]"
                        value="{{ old("{$name}.{$locale->code}", $value[$locale->code] ?? '') }}"
                        @if ($compact && $locale->code === $defaultCode) aria-label="{{ $label }}" @endif
                        @if ($required && $locale->code === $defaultCode) required @endif
                    >
                @endif
            </div>
        @endforeach
        @if ($index === 1)
            </details>
        @endif
    @endforeach
</fieldset>
