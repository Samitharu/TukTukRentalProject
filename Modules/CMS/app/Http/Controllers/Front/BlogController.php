<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\CMS\Models\BlogPost;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BlogController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::query()->published()->orderByDesc('published_at')->paginate(9);

        return view('cms::front.blog.index', compact('posts'));
    }

    public function show(string $locale, string $slug): View
    {
        $post = BlogPost::findBySlug($locale, $slug);

        if ($post === null || ! $post->is_published) {
            throw new NotFoundHttpException;
        }

        return view('cms::front.blog.show', compact('post'));
    }
}
