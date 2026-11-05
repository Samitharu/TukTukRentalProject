{{--
    A package's cover that opens all its photos (package-gallery.js). Options:
    $class for the button, $photoClass for the picture, $thumb to use the small
    copy, $compact to show only the number on small photos.
--}}
@php $cover = $package->primaryImage(); @endphp
@if ($cover)
    @php $photos = $package->galleryImages(); @endphp
    <button
        type="button"
        class="gallery-trigger {{ $class ?? '' }}"
        data-gallery="{{ json_encode($photos->map(fn ($photo) => $photo->url())->all()) }}"
        data-gallery-title="{{ $package->name }}"
        aria-label="{{ __('core::front.gallery_open', ['package' => $package->name]) }}"
    >
        <img src="{{ ($thumb ?? false) ? $cover->thumbUrl() : $cover->url() }}" alt="{{ $package->name }}" loading="lazy" class="{{ $photoClass ?? '' }}">
        <span class="gallery-trigger__count">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 8h3l1.5-2.5h7L17 8h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="13" r="3.2" stroke="currentColor" stroke-width="1.8"/></svg>
            {{ ($compact ?? false) ? $photos->count() : trans_choice('core::front.packages_photos', $photos->count(), ['count' => $photos->count()]) }}
        </span>
    </button>
@endif
