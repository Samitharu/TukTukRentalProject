@php $post = $post ?? null; @endphp

<x-admin::translatable-field name="title" :label="__('Title')" :value="$post?->getTranslations('title') ?? []" :required="true" />
<x-admin::translatable-field name="excerpt" :label="__('Excerpt')" :value="$post?->getTranslations('excerpt') ?? []" :textarea="true" />
<x-admin::translatable-field name="body" :label="__('Body')" :value="$post?->getTranslations('body') ?? []" :textarea="true" :required="true" />

<div class="admin-form-field">
    <label for="published_at">{{ __('Publish date (blank = now)') }}</label>
    <input type="date" id="published_at" name="published_at" value="{{ old('published_at', $post?->published_at?->toDateString()) }}">
</div>

<div class="admin-form-field">
    <label><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $post?->is_published))> {{ __('Published') }}</label>
</div>
