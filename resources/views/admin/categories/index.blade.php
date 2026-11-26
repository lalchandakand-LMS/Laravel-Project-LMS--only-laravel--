@extends('layouts.admin')
@section('title', 'Category Builder')
@section('heading', 'Category & Subcategory Builder')
@section('actions')
    <a href="{{ route('admin.categories.create') }}" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-bold text-white">+ Create category</a>
@endsection
@section('content')
<div class="mb-5 grid gap-3 sm:grid-cols-3">
    <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Categories shown</p><p class="mt-1 text-2xl font-extrabold">{{ $categories->total() }}</p></div>
    <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Page</p><p class="mt-1 text-2xl font-extrabold">{{ $categories->currentPage() }} / {{ $categories->lastPage() }}</p></div>
    <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Management</p><p class="mt-1 text-sm font-bold">Create · Edit · Nested subcategories · Archive</p></div>
</div>
<form method="GET" class="mb-4 flex gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Search category..." class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Search</button>
</form>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto"><table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Category</th><th class="px-4 py-3">Parent</th><th class="px-4 py-3">Courses</th><th class="px-4 py-3">Subcategories</th><th class="px-4 py-3">Order</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($categories as $category)
            <tr>
                <td class="px-4 py-3"><div class="flex items-center gap-3">@if($category->image)<img src="{{ $category->image_url }}" class="h-10 w-10 rounded-lg object-cover" alt="">@else<span class="text-xl">{{ $category->icon ?: '📚' }}</span>@endif<div><p class="font-bold text-slate-800">{{ $category->name }}</p><p class="text-xs text-slate-500">/{{ $category->slug }}</p></div></div></td>
                <td class="px-4 py-3 text-slate-500">{{ $category->parent?->name ?? '— Root —' }}</td>
                <td class="px-4 py-3">{{ $category->course_links_count }}</td><td class="px-4 py-3">{{ $category->children_count }}</td><td class="px-4 py-3">{{ $category->position }}</td>
                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-bold {{ $category->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $category->is_active ? 'Active' : 'Hidden' }}</span></td>
                <td class="px-4 py-3 text-right whitespace-nowrap"><a href="{{ route('admin.categories.create', ['parent_id' => $category->id]) }}" class="font-semibold text-orange-700">+ Child</a><a href="{{ route('admin.categories.edit', $category) }}" class="ml-3 font-semibold text-emerald-700">Edit</a><form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="inline" onsubmit="return confirm('Archive this category? Linked courses and child categories will be reassigned safely.')">@csrf @method('DELETE')<button class="ml-3 font-semibold text-rose-600">Delete</button></form></td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No categories yet. Create your first category.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
<div class="mt-4">{{ $categories->links() }}</div>
@endsection
