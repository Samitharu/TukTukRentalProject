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
        $faqs = Faq::query()->active()->orderBy('sort_order')->get()->groupBy('category');

        return view('cms::front.faq', compact('faqs'));
    }
}
