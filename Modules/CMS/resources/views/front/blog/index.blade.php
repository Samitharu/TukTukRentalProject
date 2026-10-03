<x-core::layouts.public :title="__('core::front.blog_title').' · '.config('app.name')" :description="__('core::front.blog_intro')">
    <section class="section">
        <div class="container">
            <h1>{{ __('core::front.blog_title') }}</h1>
            <p class="text-muted">{{ __('core::front.blog_intro') }}</p>

            @if ($posts->isEmpty())
                <p>{{ __('core::front.blog_no_posts') }}</p>
            @else
                <div class="grid grid--3">
                    @foreach ($posts as $post)
                        <article class="card">
                            @if ($post->cover_image)
                                <img src="{{ asset('storage/'.$post->cover_image) }}" alt="" loading="lazy" style="aspect-ratio:16/9;object-fit:cover;width:100%;">
                            @endif
                            <div class="card__body">
                                <h2 style="font-size:var(--font-size-lg);">{{ $post->title }}</h2>
                                <p class="text-muted">{{ $post->excerpt }}</p>
                                <a href="{{ route('blog.show', $post->slugFor(app()->getLocale()) ?? $post->id) }}">{{ __('core::front.blog_read_more') }} &rarr;</a>
                            </div>
                        </article>
                    @endforeach
                </div>
                {{ $posts->links() }}
            @endif
        </div>
    </section>
</x-core::layouts.public>
