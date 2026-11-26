@extends('layouts.admin')
@section('title', 'Posts')
@section('heading', 'Post Management')
@section('actions')
<a href="{{ route('admin.posts.create') }}" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-bold text-white">+ Create post</a>
@endsection
@section('content')
<form method="GET" class="mb-4 flex flex-wrap gap-2">
<input name="q" value="{{ request('q') }}" placeholder="Search posts..." class="w-full max-w-sm rounded-lg border px-3 py-2 text-sm">
<select name="status" class="rounded-lg border px-3 py-2 text-sm"><option value="">All statuses</option><option value="published" @selected(request('status')==='published')>Published</option><option value="draft" @selected(request('status')==='draft')>Draft</option></select>
<button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button>
</form>
<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white"><table class="w-full text-sm">
<thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="p-3">Post</th><th class="p-3">Category</th><th class="p-3">Author</th><th class="p-3">Views</th><th class="p-3">Status</th><th class="p-3 text-right">Actions</th></tr></thead>
<tbody class="divide-y">@forelse($posts as $post)<tr><td class="p-3"><p class="font-bold">{{ $post->title }}</p><p class="text-xs text-slate-500">/{{ $post->slug }}</p></td><td class="p-3">{{ $post->category?->name ?? '—' }}</td><td class="p-3">{{ $post->author?->name ?? '—' }}</td><td class="p-3">{{ number_format($post->views_count) }}</td><td class="p-3">{{ $post->is_published ? 'Published' : 'Draft' }}</td><td class="p-3 text-right whitespace-nowrap">@if($post->is_published)<a class="text-slate-600" target="_blank" href="{{ route('blog.show',$post) }}">View</a>@endif<a class="ml-3 font-semibold text-emerald-700" href="{{ route('admin.posts.edit',$post) }}">Edit</a><form class="inline" method="POST" action="{{ route('admin.posts.destroy',$post) }}" onsubmit="return confirm('Archive this post?')">@csrf @method('DELETE')<button class="ml-3 text-rose-600">Delete</button></form></td></tr>@empty<tr><td colspan="6" class="p-8 text-center text-slate-500">No posts yet.</td></tr>@endforelse</tbody></table></div>
<div class="mt-4">{{ $posts->links() }}</div>
@endsection