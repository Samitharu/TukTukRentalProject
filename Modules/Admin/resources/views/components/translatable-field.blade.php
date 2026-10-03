@props(['name', 'label', 'value' => [], 'textarea' => false, 'required' => false])
@php
    $locales = \Modules\Localization\Models\Locale::activeCached();
    $defaultCode = \Modules\Localization\Models\Locale::defaultCode();
@endphp
<fieldset class="admin-form-field">
    <legend style="font-weight:600;margin-bottom:0.5rem;">{{ $label }}</legend>
    @foreach ($locales as $locale)
        @php $fieldId = "{$name}_{$locale->code}"; @endphp
        <div style="margin-bottom:0.5rem;">
            <label for="{{ $fieldId }}" style="font-weight:400;font-size:0.85rem;color:#5b6b64;">
                {{ $locale->native_name }} ({{ strtoupper($locale->code) }})
                @if ($required && $locale->code === $defaultCode)
                    <span style="color:#b3261e;">*</span>
                @endif
            </label>
            @if ($textarea)
                <textarea
                    id="{{ $fieldId }}"
                    name="{{ $name }}[{{ $locale->code }}]"
                    rows="3"
                    @if ($required && $locale->code === $defaultCode) required @endif
                >{{ old("{$name}.{$locale->code}", $value[$locale->code] ?? '') }}</textarea>
            @else
                <input
                    type="text"
                    id="{{ $fieldId }}"
                    name="{{ $name }}[{{ $locale->code }}]"
                    value="{{ old("{$name}.{$locale->code}", $value[$locale->code] ?? '') }}"
                    @if ($required && $locale->code === $defaultCode) required @endif
                >
            @endif
        </div>
    @endforeach
</fieldset>
