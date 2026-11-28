@extends('layouts.app')
@section('title', isset($activeCategory) ? $activeCategory->name : 'All courses')
@section('content')
<div class="aec-public-page mx-auto max-w-7xl">
    @include('partials.public-page-hero', [
        'eyebrow' => isset($activeCategory) ? 'Explore this learning path' : 'Find your next course',
        'title' => isset($activeCategory) ? $activeCategory->name : 'Courses for your next step',
        'description' => isset($activeCategory) ? 'Explore lessons and learning programs in '.$activeCategory->name.'.' : 'Choose a guided course, build practical skills and learn at a pace that works for you.',
    ])

    @if ($categories->isNotEmpty())
        <nav aria-label="Course categories" class="mt-6 flex gap-2 overflow-x-auto pb-2">
            <a href="{{ route('courses.index') }}" class="shrink-0 rounded-full border px-4 py-2 text-sm font-bold transition {{ !isset($activeCategory) && empty($filters['category']) ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-400' }}">All courses</a>
            @foreach ($categories as $category)
                <a href="{{ route('categories.show', $category->slug) }}" class="shrink-0 rounded-full border px-4 py-2 text-sm font-bold transition {{ isset($activeCategory) && $activeCategory->id === $category->id ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-400' }}">{{ $category->name }} <span class="opacity-70">{{ ($category->direct_published_courses_count ?? 0) + ($category->additional_published_courses_count ?? 0) }}</span></a>
            @endforeach
        </nav>
    @endif

    <form method="GET" class="mt-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4 lg:p-5">
        <label class="sr-only" for="course-search">Search courses</label>
        <input id="course-search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search courses…" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
        <label class="sr-only" for="course-category">Course category</label>
        <select id="course-category" name="category" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }} ({{ ($category->direct_published_courses_count ?? 0) + ($category->additional_published_courses_count ?? 0) }})</option>
            @endforeach
        </select>
        <label class="sr-only" for="course-level">Course level</label>
        <select id="course-level" name="level" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
            <option value="">Any level</option>
            @foreach (['beginner', 'intermediate', 'advanced'] as $level)
                <option value="{{ $level }}" @selected(($filters['level'] ?? '') === $level)>{{ ucfirst($level) }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <label class="sr-only" for="course-sort">Sort courses</label>
            <select id="course-sort" name="sort" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
                <option value="">Newest</option>
                <option value="popular" @selected(($filters['sort'] ?? '') === 'popular')>Most popular</option>
                <option value="rating" @selected(($filters['sort'] ?? '') === 'rating')>Top rated</option>
                <option value="price_low" @selected(($filters['sort'] ?? '') === 'price_low')>Price ↑</option>
                <option value="price_high" @selected(($filters['sort'] ?? '') === 'price_high')>Price ↓</option>
            </select>
            <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-slate-700">Search</button>
        </div>
    </form>

    <div class="mb-5 mt-8 flex items-end justify-between gap-4">
        <div><p class="text-xs font-extrabold uppercase tracking-[.16em] text-emerald-700">Learning library</p><h2 class="mt-1 text-2xl font-extrabold text-slate-900">{{ $courses->total() }} courses available</h2></div>
    </div>
    @if ($courses->count())
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($courses as $course)
                <div class="aec-motion-card rounded-2xl">@include('partials.course-card', ['course' => $course])</div>
            @endforeach
        </div>
        <div class="mt-10">{{ $courses->links() }}</div>
    @else
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center sm:p-16">
            <p class="text-4xl" aria-hidden="true">🔍</p>
            <h3 class="mt-3 text-lg font-bold text-slate-900">No courses found</h3>
            <p class="mt-1 text-sm text-slate-600">Try a different keyword or clear the filters.</p>
            <a href="{{ route('courses.index') }}" class="mt-5 inline-block rounded-full bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">Reset filters</a>
        </div>
    @endif
</div>
@endsection
