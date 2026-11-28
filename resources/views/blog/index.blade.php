@extends('layouts.app')
@section('title','Blog & News')
@section('content')
<div class="aec-public-page mx-auto max-w-7xl">
    @include('partials.public-page-hero', [
        'eyebrow' => 'Ideas for your learning journey',
        'title' => 'Blog & learning resources',
        'description' => 'Explore learning tips, center announcements and practical English resources from our team.',
    ])
    <div class="mt-6 flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div><p class="text-xs font-extrabold uppercase tracking-[.16em] text-orange-700">From the learning desk</p><h2 class="mt-1 text-2xl font-extrabold text-slate-900">Latest articles</h2></div>
        <form method="GET" class="flex w-full gap-2 sm:w-auto">
            <label class="sr-only" for="blog-search">Search posts</label>
            <input id="blog-search" name="q" value="{{ request('q') }}" placeholder="Search articles…" class="min-w-0 flex-1 rounded-full border border-slate-300 px-4 py-2 text-sm sm:w-64">
            <button class="rounded-full bg-slate-900 px-5 py-2 text-sm font-bold text-white transition hover:bg-slate-700">Search</button>
        </form>
    </div>
    <nav aria-label="Blog categories" class="my-5 flex gap-2 overflow-x-auto pb-2">
        <a href="{{ route('blog.index') }}" class="shrink-0 rounded-full border px-4 py-2 text-sm font-bold transition {{ !request('category') ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-400' }}">All posts</a>
        @foreach($categories as $category)
            <a href="{{ route('blog.index',['category'=>$category->slug]) }}" class="shrink-0 rounded-full border px-4 py-2 text-sm font-bold transition {{ request('category') === $category->slug ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-400' }}">{{ $category->name }}</a>
        @endforeach
    </nav>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($posts as $post)
            <article class="aec-motion-card overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                @if($post->thumbnail)<a href="{{ route('blog.show',$post) }}" class="block overflow-hidden bg-slate-100"><img src="{{ media_url($post->thumbnail) }}" alt="{{ $post->title }}" loading="lazy" class="h-52 w-full object-cover transition duration-500 hover:scale-[1.04]"></a>@endif
                <div class="p-5">
                    <div class="flex items-center justify-between gap-2 text-xs text-slate-500"><span>{{ $post->published_at?->format('d M Y') }}</span>@if($post->category)<span class="rounded-full bg-orange-50 px-3 py-1 font-bold text-orange-700">{{ $post->category->name }}</span>@endif</div>
                    <h3 class="mt-3 text-xl font-extrabold leading-snug text-slate-900"><a href="{{ route('blog.show',$post) }}" class="hover:text-orange-700">{{ $post->title }}</a></h3>
                    @if($post->excerpt)<p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">{{ $post->excerpt }}</p>@endif
                    <a class="mt-4 inline-flex items-center gap-2 font-bold text-orange-700" href="{{ route('blog.show',$post) }}">Read article <span aria-hidden="true">→</span></a>
                </div>
            </article>
        @empty
            <p class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">No published posts yet.</p>
        @endforelse
    </div>
    <div class="mt-8">{{ $posts->links() }}</div>
</div>
@endsection
