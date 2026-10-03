@php $faq = $faq ?? null; @endphp

<x-admin::translatable-field name="question" :label="__('Question')" :value="$faq?->getTranslations('question') ?? []" :required="true" />
<x-admin::translatable-field name="answer" :label="__('Answer')" :value="$faq?->getTranslations('answer') ?? []" :textarea="true" :required="true" />

<div class="admin-form-field">
    <label for="category">{{ __('Category (optional)') }}</label>
    <input type="text" id="category" name="category" value="{{ old('category', $faq?->category) }}">
</div>

<div class="admin-form-field">
    <label for="sort_order">{{ __('Sort order') }}</label>
    <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $faq?->sort_order ?? 0) }}" min="0" required>
</div>

<div class="admin-form-field">
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $faq?->is_active ?? true))> {{ __('Active') }}</label>
</div>
