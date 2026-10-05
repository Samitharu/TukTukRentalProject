<x-core::layouts.public :title="__('core::front.faq_title').' · '.config('app.name')" :description="__('core::front.faq_meta_description')" :schema="$schema">
    <section class="section">
        <div class="container" style="max-width:42rem;">
            <h1>{{ __('core::front.faq_title') }}</h1>

            @foreach ($faqs as $category => $items)
                @if ($category)
                    <h2 style="font-size:var(--font-size-lg);margin-top:2rem;">{{ $category }}</h2>
                @endif
                <div x-data="{ open: null }">
                    @foreach ($items as $faq)
                        <div class="card" style="margin-bottom:0.75rem;">
                            <button
                                type="button"
                                @click="open = open === {{ $faq->id }} ? null : {{ $faq->id }}"
                                style="width:100%;text-align:left;background:none;border:none;padding:1rem;font-weight:700;cursor:pointer;display:flex;justify-content:space-between;align-items:center;min-height:44px;color:var(--color-text);font-size:1rem;"
                                :aria-expanded="open === {{ $faq->id }}"
                            >
                                {{ $faq->question }}
                                <span aria-hidden="true" x-text="open === {{ $faq->id }} ? '−' : '+'"></span>
                            </button>
                            <div x-show="open === {{ $faq->id }}" x-cloak style="padding:0 1rem 1rem;">
                                {{ $faq->answer }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>
</x-core::layouts.public>
