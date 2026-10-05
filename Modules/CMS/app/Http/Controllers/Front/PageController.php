<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\CMS\Models\Page;
use Modules\Core\Support\Seo;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PageController extends Controller
{
    public function show(string $locale, string $slug): View
    {
        $page = Page::findBySlug($locale, $slug);

        if ($page === null || ! $page->is_published) {
            throw new NotFoundHttpException;
        }

        $page->load('routeSlugs');

        return view('cms::front.page', [
            'page' => $page,
            'alternates' => Seo::alternatesForModel($page, 'pages.show'),
            'schema' => [Seo::breadcrumbs([
                [__('core::front.nav_home'), route('home')],
                [(string) $page->title, url()->current()],
            ])],
        ]);
    }
}
