@extends('layouts.admin')
@section('title', 'Newsletter Subscribers')
@section('heading', 'Newsletter Management')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="grid gap-3 sm:grid-cols-3">
        @foreach(['pending'=>'Awaiting confirmation','subscribed'=>'Confirmed subscribers','unsubscribed'=>'Unsubscribed'] as $key => $label)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-black text-slate-900">{{ number_format($counts[$key] ?? 0) }}</p></div>
        @endforeach
    </div>
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" class="grid gap-3 md:grid-cols-[1fr_220px_auto_auto]">
            <input name="q" value="{{ request('q') }}" placeholder="Search email address" class="rounded-lg border-slate-300 text-sm">
            <select name="status" class="rounded-lg border-slate-300 text-sm"><option value="">All statuses</option>@foreach(['pending','subscribed','unsubscribed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Filter</button>
            <a href="{{ route('admin.marketing.newsletter.index', array_merge(request()->query(), ['export'=>'csv'])) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-center text-sm font-bold text-white">Export CSV</a>
        </form>
    </section>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="divide-y divide-slate-100">
            @forelse($subscribers as $subscriber)
                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div><p class="font-semibold text-slate-900">{{ $subscriber->email }}</p><p class="mt-1 text-xs text-slate-500">{{ $subscriber->name ?: 'Name not provided' }} · {{ $subscriber->created_at->format('d M Y') }}</p></div>
                    <div class="flex items-center gap-3"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ ucfirst($subscriber->status) }}</span>
                        <form method="POST" action="{{ route('admin.marketing.newsletter.destroy', $subscriber) }}" onsubmit="return confirm('Delete this subscriber record?')">@csrf @method('DELETE')<button class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700">Delete</button></form>
                    </div>
                </div>
            @empty
                <p class="p-8 text-center text-sm text-slate-500">No newsletter subscribers found.</p>
            @endforelse
        </div>
        <div class="border-t border-slate-100 p-4">{{ $subscribers->links() }}</div>
    </section>
    <p class="rounded-xl bg-amber-50 p-4 text-xs leading-5 text-amber-900">Double opt-in is enabled: only subscribers who confirm the email link are marked subscribed. Bulk newsletter campaign sending and email-provider delivery analytics are not part of this subscriber register.</p>
</div>
@endsection
