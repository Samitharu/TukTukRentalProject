<x-core::layouts.public :title="$page->title.' · '.config('app.name')" :description="\Illuminate\Support\Str::limit(strip_tags((string) $page->content), 160)">
    <section class="section">
        <div class="container" style="max-width:42rem;">
            <h1>{{ $page->title }}</h1>
            <div>{!! $page->content !!}</div>
        </div>
    </section>
</x-core::layouts.public>
