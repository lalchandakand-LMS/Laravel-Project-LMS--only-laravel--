@extends(auth()->user()->role === 'student' ? 'layouts.student' : (auth()->user()->role === 'admin' ? 'layouts.admin' : 'layouts.portal'))
@section('title', $announcement->title)
@section('heading', 'Notice details')
@section('content')
<article class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="rounded-full bg-slate-100 px-2 py-1 font-bold">{{ strtoupper($announcement->priority) }}</span>
        <span class="text-slate-400">{{ optional($announcement->publish_at ?? $announcement->created_at)->format('d M Y, h:i A') }}</span>
        @if($announcement->is_pinned)<span class="font-bold text-amber-700">Pinned</span>@endif
    </div>
    <h2 class="mt-4 text-2xl font-black text-slate-900">{{ $announcement->title }}</h2>
    <p class="mt-2 text-xs text-slate-500">{{ $announcement->batch?->name ?? $announcement->course?->title ?? 'All relevant users' }}</p>
    <div class="mt-6 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $announcement->body }}</div>
    <a href="{{ route('notices.index') }}" class="mt-8 inline-block rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700">← Back to notices</a>
</article>
@endsection
