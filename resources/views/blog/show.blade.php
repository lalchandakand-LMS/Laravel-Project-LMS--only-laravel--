@extends('layouts.app')
@section('title', $post->meta_title ?: $post->title)
@section('meta_description', $post->meta_description ?: $post->excerpt)
@section('meta_image', $post->thumbnail ? media_url($post->thumbnail) : '')
@section('meta_image_alt', $post->title)
@section('og_type', 'article')
@push('structured_data')
<script type="application/ld+json"><?php echo json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $post->meta_title ?: $post->title,
    'description' => $post->meta_description ?: $post->excerpt,
    'datePublished' => $post->published_at?->toIso8601String(),
    'dateModified' => $post->updated_at?->toIso8601String(),
    'author' => ['@type' => 'Person', 'name' => $post->author?->name ?: setting('site_name', "Alom's English Center")],
    'publisher' => ['@type' => 'Organization', 'name' => setting('site_name', "Alom's English Center"), 'url' => config('app.url')],
    'mainEntityOfPage' => route('blog.show', $post),
    'image' => $post->thumbnail ? media_url($post->thumbnail) : null,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
@endpush

@section('content')
<article class="mx-auto max-w-4xl px-4 py-10">
    <a href="{{ route('blog.index') }}" class="text-sm font-bold text-orange-700">← Blog & News</a>
    <p class="mt-6 text-sm text-slate-500">{{ $post->published_at?->format('d M Y') }} · {{ number_format($post->views_count) }} views</p>
    <h1 class="mt-2 text-3xl font-extrabold">{{ $post->title }}</h1>
    @if($post->excerpt)
        <p class="mt-4 text-lg text-slate-600">{{ $post->excerpt }}</p>
    @endif
    @if($post->thumbnail)
        <img src="{{ media_url($post->thumbnail) }}" alt="{{ $post->title }}" class="mt-6 max-h-[480px] w-full rounded-2xl object-cover">
    @endif
    @if($post->video_url)
        <div class="mt-6">
            <a href="{{ $post->video_url }}" target="_blank" rel="noopener noreferrer" class="font-bold text-orange-700">Watch related video ↗</a>
        </div>
    @endif
    <div class="prose prose-slate mt-8 max-w-none whitespace-pre-line">{{ $post->content }}</div>
    @if($post->tags)
        <div class="mt-8 flex flex-wrap gap-2">
            @foreach($post->tags as $tag)
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs">{{ $tag }}</span>
            @endforeach
        </div>
    @endif
    @if($related->isNotEmpty())
        <section class="mt-12 border-t pt-8">
            <h2 class="text-xl font-bold">Related posts</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @foreach($related as $item)
                    <a href="{{ route('blog.show',$item) }}" class="rounded-xl border p-4 font-semibold">{{ $item->title }}</a>
                @endforeach
            </div>
        </section>
    @endif
</article>
@endsection
