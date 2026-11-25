@extends(auth()->user()->role === 'student' ? 'layouts.student' : (auth()->user()->role === 'admin' ? 'layouts.admin' : 'layouts.portal'))
@section('title', 'Notice Board')
@section('heading', 'Notice Board')
@section('content')
<div class="mx-auto max-w-5xl space-y-3">
    <p class="text-sm text-slate-500">Course updates, class schedules, examinations and important announcements.</p>
    @forelse($notices as $notice)
        <a href="{{ route('notices.show', $notice) }}" class="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-emerald-300">
            <div class="flex flex-wrap items-center gap-2">
                @if($notice->is_pinned)<span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold text-amber-800">PINNED</span>@endif
                <span class="rounded-full px-2 py-1 text-[10px] font-bold {{ $notice->priority === 'urgent' ? 'bg-rose-100 text-rose-700' : ($notice->priority === 'important' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">{{ strtoupper($notice->priority) }}</span>
                @if(!in_array($notice->id, $readIds))<span class="text-[10px] font-bold text-emerald-700">NEW</span>@endif
                <span class="ml-auto text-xs text-slate-400">{{ optional($notice->publish_at ?? $notice->created_at)->format('d M Y, h:i A') }}</span>
            </div>
            <h2 class="mt-2 text-lg font-bold text-slate-900">{{ $notice->title }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit(strip_tags($notice->body), 180) }}</p>
            <p class="mt-3 text-xs font-semibold text-emerald-700">{{ $notice->batch?->name ?? $notice->course?->title ?? 'General notice' }} · View notice →</p>
        </a>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">There are no active notices for your account.</div>
    @endforelse
    {{ $notices->links() }}
</div>
@endsection
