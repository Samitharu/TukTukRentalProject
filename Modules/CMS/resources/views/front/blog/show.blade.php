<x-core::layouts.public :title="$post->title.' · '.config('app.name')" :description="$post->excerpt ?: $post->body" :alternates="$alternates" :schema="$schema" :image="$image" type="article">
    <section class="section">
        <div class="container" style="max-width:42rem;">
            <p><a href="{{ route('blog.index') }}">&larr; {{ __('core::front.blog_back_to_list') }}</a></p>
            <h1>{{ $post->title }}</h1>
            @if ($post->cover_image)
                <img src="{{ asset('storage/'.$post->cover_image) }}" alt="" style="border-radius:var(--radius-md);width:100%;aspect-ratio:16/9;object-fit:cover;margin-bottom:1.5rem;">
            @endif
            <div>{!! $post->body !!}</div>
        </div>
    </section>
</x-core::layouts.public>
