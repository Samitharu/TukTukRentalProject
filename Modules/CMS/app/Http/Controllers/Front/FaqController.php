<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\CMS\Models\Faq;

final class FaqController extends Controller
{
    public function index(): View
    {
        $faqs = Faq::query()->active()->orderBy('sort_order')->get();

        // schema.org FAQPage: every question with its answer, so search
        // engines can match the page to the exact question asked.
        $schema = $faqs->isEmpty() ? [] : [[
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->map(fn (Faq $faq) => [
                '@type' => 'Question',
                'name' => (string) $faq->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags((string) $faq->answer))],
            ])->values()->all(),
        ]];

        return view('cms::front.faq', [
            'faqs' => $faqs->groupBy('category'),
            'schema' => $schema,
        ]);
    }
}
