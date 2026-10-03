<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\CMS\Http\Requests\Admin\StoreBlogPostRequest;
use Modules\CMS\Models\BlogPost;

final class BlogPostController extends Controller
{
    public function index(): View
    {
        $this->authorize('cms.view');

        $posts = BlogPost::query()->orderByDesc('id')->get();

        return view('cms::admin.blog.index', compact('posts'));
    }

    public function create(): View
    {
        $this->authorize('cms.manage');

        return view('cms::admin.blog.create');
    }

    public function store(StoreBlogPostRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');
        $data['author_id'] = $request->user()->id;

        BlogPost::query()->create($data);

        return redirect()->route('admin.blog.index')->with('status', __('Post created.'));
    }

    public function edit(BlogPost $post): View
    {
        $this->authorize('cms.manage');

        return view('cms::admin.blog.edit', compact('post'));
    }

    public function update(StoreBlogPostRequest $request, BlogPost $post): RedirectResponse
    {
        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');

        $post->update($data);

        return redirect()->route('admin.blog.index')->with('status', __('Post updated.'));
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $this->authorize('cms.manage');

        $post->routeSlugs()->delete();
        $post->delete();

        return redirect()->route('admin.blog.index')->with('status', __('Post removed.'));
    }
}
