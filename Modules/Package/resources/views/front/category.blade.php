<x-core::layouts.public :title="$category->name.' · '.config('app.name')" :description="$category->description ?: __('core::front.packages_intro')" :alternates="$alternates" :schema="$schema" :image="$image">
    <section class="section">
        <div class="container">
            @include('package::front._category-tabs', ['tabs' => $tabs, 'current' => $category])

            <header class="package-category-hero">
                @if ($category->imageUrl())
                    <img src="{{ $category->imageUrl() }}" alt="" class="package-category-hero__image">
                @endif
                <div>
                    <h1>{{ $category->name }}</h1>
                    @if ($category->description)
                        <p class="text-muted">{{ $category->description }}</p>
                    @endif
                    @if ($category->isActivity())
                        <p class="text-muted">{{ __('core::front.packages_activity_note') }}</p>
                    @endif
                </div>
            </header>

            @if ($category->packages->isEmpty())
                <p class="text-muted">{{ __('core::front.packages_category_empty') }}</p>
            @else
                <div class="grid grid--3">
                    @foreach ($category->packages as $package)
                        @include('package::front._card', ['package' => $package])
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-core::layouts.public>
