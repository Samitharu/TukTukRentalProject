<x-core::layouts.public :title="__('core::front.packages_title').' · '.config('app.name')" :description="__('core::front.packages_intro')">
    <section class="section">
        <div class="container">
            <h1>{{ __('core::front.packages_title') }}</h1>
            <p class="text-muted">{{ __('core::front.packages_intro') }}</p>

            @include('package::front._category-tabs', ['tabs' => $tabs, 'current' => null])

            @foreach ($categories as $category)
                @php $categorySlug = $category->slugFor(app()->getLocale()); @endphp
                <section class="package-category" aria-labelledby="category-{{ $category->id }}">
                    <div class="package-category__head">
                        <h2 id="category-{{ $category->id }}">{{ $category->name }}</h2>
                        @if ($categorySlug !== null)
                            <a href="{{ route('packages.category', $categorySlug) }}">{{ __('core::front.packages_view_details') }} &rarr;</a>
                        @endif
                    </div>
                    <div class="grid grid--3">
                        @foreach ($category->packages as $package)
                            @include('package::front._card', ['package' => $package])
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </section>
</x-core::layouts.public>
