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
    <p class="mt-2 text-xs text-slate-500">{{ $announcement->branch?->name ?? 'All branches' }} · {{ $announcement->batch?->name ?? $announcement->course?->title ?? 'All relevant users' }}</p>
    <div class="prose prose-slate mt-6 max-w-none text-sm leading-7">{!! \Illuminate\Support\Str::markdown($announcement->body, [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ]) !!}</div>
    @if($announcement->attachments->isNotEmpty())
        <section class="mt-8 border-t border-slate-200 pt-5">
            <h3 class="font-bold text-slate-900">Attachments</h3>
            <ul class="mt-3 space-y-2">
                @foreach($announcement->attachments as $attachment)
                    <li><a href="{{ route('notices.attachments.download', $attachment) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:underline">↓ {{ $attachment->original_name }} <span class="text-xs font-normal text-slate-400">({{ number_format($attachment->size / 1024, 1) }} KB)</span></a></li>
                @endforeach
            </ul>
        </section>
    @endif
    <a href="{{ route('notices.index') }}" class="mt-8 inline-block rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700">← Back to notices</a>
</article>
@endsection
