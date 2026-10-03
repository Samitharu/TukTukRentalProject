@php $page = $page ?? null; @endphp

<x-admin::translatable-field name="title" :label="__('Title')" :value="$page?->getTranslations('title') ?? []" :required="true" />
<x-admin::translatable-field name="content" :label="__('Content')" :value="$page?->getTranslations('content') ?? []" :textarea="true" />

<div class="admin-form-field">
    <label for="template">{{ __('Template') }}</label>
    <input type="text" id="template" name="template" value="{{ old('template', $page?->template ?? 'default') }}" required>
</div>

<div class="admin-form-field">
    <label><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $page?->is_published ?? true))> {{ __('Published') }}</label>
</div>
