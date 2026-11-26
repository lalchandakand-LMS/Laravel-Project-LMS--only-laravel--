<?php

namespace App\\Http\\Controllers;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Category;
use App\\Models\\Post;
use Illuminate\\Http\\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::published()->with(['category', 'author'])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('category'), fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $request->category)->where('is_active', true)))
            ->latest('published_at')->paginate(12)->withQueryString();

        return view('blog.index', [
            'posts' => $posts,
            'categories' => Category::where('is_active', true)->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function show(Post $post)
    {
        abort_unless($post->is_published && $post->published_at && $post->published_at->lte(now()), 404);
        $post->increment('views_count');

        return view('blog.show', [
            'post' => $post->load(['category', 'author']),
            'related' => Post::published()->where('id', '!=', $post->id)
                ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
                ->latest('published_at')->take(3)->get(),
        ]);
    }
}