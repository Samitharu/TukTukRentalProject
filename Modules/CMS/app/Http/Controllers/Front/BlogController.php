<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\CMS\Models\BlogPost;
use Modules\Core\Support\Seo;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BlogController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::query()
            ->published()
            ->with(['routeSlugs' => fn ($q) => $q->where('locale', app()->getLocale())])
            ->orderByDesc('published_at')
            ->paginate(9);

        return view('cms::front.blog.index', compact('posts'));
    }

    public function show(string $locale, string $slug): View
    {
        $post = BlogPost::findBySlug($locale, $slug);

        if ($post === null || ! $post->is_published) {
            throw new NotFoundHttpException;
        }

        $post->load(['routeSlugs', 'author']);
        $image = Seo::storageImage($post->cover_image);

        $article = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => (string) $post->title,
            'description' => Seo::description((string) ($post->excerpt ?: $post->body)),
            'image' => $image,
            'datePublished' => ($post->published_at ?? $post->created_at)?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'inLanguage' => $locale,
            'mainEntityOfPage' => url()->current(),
            'author' => $post->author !== null
                ? ['@type' => 'Person', 'name' => $post->author->name]
                : ['@type' => 'Organization', 'name' => config('app.name')],
            'publisher' => ['@type' => 'Organization', 'name' => config('app.name'), 'url' => route('home')],
        ], fn ($value) => $value !== null);

        return view('cms::front.blog.show', [
            'post' => $post,
            'image' => $image,
            'alternates' => Seo::alternatesForModel($post, 'blog.show'),
            'schema' => [
                $article,
                Seo::breadcrumbs([
                    [__('core::front.nav_home'), route('home')],
                    [__('core::front.blog_title'), route('blog.index')],
                    [(string) $post->title, url()->current()],
                ]),
            ],
        ]);
    }
}
