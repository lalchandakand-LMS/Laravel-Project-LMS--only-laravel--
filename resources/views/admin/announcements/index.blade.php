@extends('layouts.admin')
@section('title', 'Announcements')
@section('heading', 'Announcements')
@section('actions')
    <a href="{{ route('admin.announcements.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">+ New announcement</a>
@endsection
@section('content')
<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Recipients notified</p><p class="mt-1 text-2xl font-black text-slate-900">{{ number_format((clone $announcements)->sum('deliveries_count')) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Read receipts (current page)</p><p class="mt-1 text-2xl font-black text-slate-900">{{ number_format($announcements->sum('reads_count')) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Attachments (current page)</p><p class="mt-1 text-2xl font-black text-slate-900">{{ number_format($announcements->sum('attachments_count')) }}</p></div>
    </div>
    @forelse ($announcements as $announcement)
        @php
            $status = ! $announcement->is_published ? 'Draft' : (($announcement->publish_at && $announcement->publish_at->isFuture()) ? 'Scheduled' : (($announcement->expires_at && $announcement->expires_at->isPast()) ? 'Expired' : 'Published'));
            $unread = max(0, $announcement->deliveries_count - $announcement->reads_count);
        @endphp
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-bold text-slate-900">{{ $announcement->title }}</h3>
                        <span class="rounded-full px-2 py-1 text-[10px] font-bold {{ $status === 'Published' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $status }}</span>
                        <span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-800">{{ strtoupper($announcement->priority) }}</span>
                        @if($announcement->is_pinned)<span class="text-[10px] font-bold text-amber-700">PINNED</span>@endif
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ $announcement->branch?->name ?? 'All branches' }} · {{ $announcement->batch?->name ?? $announcement->course?->title ?? 'All courses' }} · {{ $announcement->audience_role ? ucfirst($announcement->audience_role) : 'All relevant roles' }}</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ \Illuminate\Support\Str::limit(strip_tags($announcement->body), 220) }}</p>
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                        <span>{{ number_format($announcement->deliveries_count) }} notified</span>
                        <span>{{ number_format($announcement->reads_count) }} read</span>
                        <span>{{ number_format($unread) }} unread</span>
                        <span>{{ number_format($announcement->attachments_count) }} attachments</span>
                        <span>Created {{ $announcement->created_at->format('d M Y') }}</span>
                    </div>
                </div>
                <div class="shrink-0 text-xs font-semibold">
                    <a href="{{ route('admin.announcements.edit', $announcement) }}" class="text-emerald-700">Edit</a>
                    <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" class="inline" onsubmit="return confirm('Delete this announcement and its attachments?')">
                        @csrf @method('DELETE')<button class="ml-3 text-rose-600">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">No announcements yet.</div>
    @endforelse
</div>
<div class="mt-4">{{ $announcements->links() }}</div>
@endsection
