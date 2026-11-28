<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::with(['category', 'author'])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('is_published', $request->status === 'published'))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.posts.index', compact('posts'));
    }

    public function create()
    {
        return view('admin.posts.form', ['post' => new Post(), 'categories' => Category::where('is_active', true)->orderBy('position')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['author_id'] = $request->user()->id;
        $data['tags'] = $this->tags($request->input('tags'));
        $storedThumbnail = null;
        if ($request->hasFile('thumbnail')) {
            $storedThumbnail = $request->file('thumbnail')->store('posts', 'public');
            if ($storedThumbnail) {
                MediaAsset::registerUpload('Post thumbnail: '.$data['title'], 'public', $storedThumbnail, $request->file('thumbnail'), $request->user()->id);
            }
            if (! $storedThumbnail) {
                throw ValidationException::withMessages(['thumbnail' => 'The post thumbnail could not be stored.']);
            }
            $data['thumbnail'] = $storedThumbnail;
        } elseif ($request->filled('thumbnail_url')) {
            $data['thumbnail'] = $request->input('thumbnail_url');
        }
        unset($data['thumbnail_url']);
        try {
            $post = Post::create($data);
        } catch (\Throwable $exception) {
            if ($storedThumbnail) {
                delete_stored_media('public', $storedThumbnail);
            }
            throw $exception;
        }

        return redirect()->route('admin.posts.edit', $post)->with('success', 'Post saved.');
    }

    public function edit(Post $post)
    {
        return view('admin.posts.form', ['post' => $post, 'categories' => Category::where('is_active', true)->orderBy('position')->orderBy('name')->get()]);
    }

    public function update(Request $request, Post $post)
    {
        $data = $this->validated($request, $post);
        $data['tags'] = $this->tags($request->input('tags'));
        $oldThumbnail = $post->thumbnail;
        $storedThumbnail = null;
        if ($request->hasFile('thumbnail')) {
            $storedThumbnail = $request->file('thumbnail')->store('posts', 'public');
            if ($storedThumbnail) {
                MediaAsset::registerUpload('Post thumbnail: '.$data['title'], 'public', $storedThumbnail, $request->file('thumbnail'), $request->user()->id);
            }
            if (! $storedThumbnail) {
                throw ValidationException::withMessages(['thumbnail' => 'The new post thumbnail could not be stored. The previous thumbnail has been kept.']);
            }
            $data['thumbnail'] = $storedThumbnail;
        } elseif ($request->filled('thumbnail_url')) {
            $data['thumbnail'] = $request->input('thumbnail_url');
        } elseif ($request->boolean('remove_thumbnail')) {
            $data['thumbnail'] = null;
        }
        unset($data['thumbnail_url'], $data['remove_thumbnail']);
        try {
            $post->update($data);
        } catch (\Throwable $exception) {
            if ($storedThumbnail) {
                delete_stored_media('public', $storedThumbnail);
            }
            throw $exception;
        }
        if ($oldThumbnail && array_key_exists('thumbnail', $data) && $oldThumbnail !== $data['thumbnail']) {
            delete_stored_media('public', $oldThumbnail);
        }

        return redirect()->route('admin.posts.edit', $post)->with('success', 'Post updated.');
    }

    public function destroy(Post $post)
    {
        $post->delete();
        return redirect()->route('admin.posts.index')->with('success', 'Post archived. Restore it from Recycle Bin if needed.');
    }

    private function validated(Request $request, ?Post $post = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:180',
            'slug' => ['nullable', 'string', 'max:200', 'alpha_dash', Rule::unique('posts', 'slug')->ignore($post?->id)],
            'category_id' => 'nullable|exists:categories,id',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'video_url' => 'nullable|url:http,https|max:2048',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:4096',
            'thumbnail_url' => 'nullable|url:http,https|max:2048',
            'tags' => 'nullable|string|max:2000',
            'meta_title' => 'nullable|string|max:180',
            'meta_description' => 'nullable|string|max:300',
        ]) + ['is_published' => $request->boolean('is_published')];
    }

    private function tags(?string $raw): array
    {
        return collect(explode(',', (string) $raw))->map(fn ($tag) => trim($tag))->filter()->unique(fn ($tag) => mb_strtolower($tag))->take(20)->values()->all();
    }
}