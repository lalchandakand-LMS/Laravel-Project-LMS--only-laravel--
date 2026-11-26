@extends('layouts.admin')
@section('title', 'Marketing Leads')
@section('heading', 'Marketing Lead Management')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="grid gap-3 grid-cols-2 md:grid-cols-3 xl:grid-cols-6">
        @foreach(['new'=>'New','contacted'=>'Contacted','qualified'=>'Qualified','converted'=>'Converted','spam'=>'Spam','closed'=>'Closed'] as $key => $label)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-black text-slate-900">{{ $counts[$key] ?? 0 }}</p></div>
        @endforeach
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" class="grid gap-3 md:grid-cols-4">
            <input name="q" value="{{ request('q') }}" placeholder="Name, email, phone or subject" class="rounded-lg border-slate-300 text-sm">
            <select name="status" class="rounded-lg border-slate-300 text-sm"><option value="">All statuses</option>@foreach(['new','contacted','qualified','converted','spam','closed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
            <select name="campaign" class="rounded-lg border-slate-300 text-sm"><option value="">All campaigns</option>@foreach($campaigns as $campaign)<option value="{{ $campaign }}" @selected(request('campaign') === $campaign)>{{ $campaign }}</option>@endforeach</select>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Filter leads</button>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 p-4"><div><h2 class="font-bold text-slate-900">Website inquiries</h2><p class="text-xs text-slate-500">Contact form inquiries and their recorded UTM campaign attribution.</p></div><span class="text-xs font-semibold text-slate-500">{{ $leads->total() }} leads</span></div>
        <div class="divide-y divide-slate-100">
            @forelse($leads as $lead)
                <article class="grid gap-4 p-4 xl:grid-cols-[1fr_1fr_1.2fr]">
                    <div>
                        <div class="flex flex-wrap items-center gap-2"><h3 class="font-bold text-slate-900">{{ $lead->name }}</h3><span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase text-slate-600">{{ $lead->status }}</span></div>
                        <p class="mt-1 text-sm text-slate-600">{{ $lead->email }}</p>
                        @if($lead->phone)<p class="text-sm text-slate-600">{{ $lead->phone }}</p>@endif
                        @if($lead->subject)<p class="mt-2 text-sm font-semibold text-slate-700">{{ $lead->subject }}</p>@endif
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $lead->message }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="mailto:{{ $lead->email }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Email</a>
                            @if($lead->phone)
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $lead->phone) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Call</a>
                            @endif
                            @if($lead->whatsapp_url)
                                <a href="{{ $lead->whatsapp_url }}" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700">WhatsApp follow-up ↗</a>
                            @endif
                        </div>
                        <p class="mt-2 text-[11px] text-slate-400">{{ $lead->created_at->format('d M Y, h:i A') }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 text-xs">
                        <p class="font-bold text-slate-700">Attribution</p>
                        <dl class="mt-2 space-y-1 text-slate-600">
                            <div><dt class="inline font-semibold">Source:</dt> <dd class="inline">{{ $lead->utm_source ?: $lead->source }}</dd></div>
                            <div><dt class="inline font-semibold">Medium:</dt> <dd class="inline">{{ $lead->utm_medium ?: '—' }}</dd></div>
                            <div><dt class="inline font-semibold">Campaign:</dt> <dd class="inline">{{ $lead->utm_campaign ?: '—' }}</dd></div>
                            <div><dt class="inline font-semibold">Term:</dt> <dd class="inline">{{ $lead->utm_term ?: '—' }}</dd></div>
                            <div><dt class="inline font-semibold">Content:</dt> <dd class="inline">{{ $lead->utm_content ?: '—' }}</dd></div>
                            <div class="break-all"><dt class="inline font-semibold">Landing:</dt> <dd class="inline">{{ $lead->landing_page ?: '—' }}</dd></div>
                            <div><dt class="inline font-semibold">Referrer:</dt> <dd class="inline">{{ $lead->referrer_host ?: 'Direct / unknown' }}</dd></div>
                        </dl>
                    </div>
                    <form method="POST" action="{{ route('admin.marketing.leads.update', $lead) }}@if(request()->getQueryString())?{{ request()->getQueryString() }}@endif" class="space-y-3">
                        @csrf @method('PATCH')
                        <div><label class="mb-1 block text-xs font-bold text-slate-600">Lead status</label><select name="status" class="w-full rounded-lg border-slate-300 text-sm">@foreach(['new','contacted','qualified','converted','spam','closed'] as $status)<option value="{{ $status }}" @selected($lead->status === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
                        <div><label class="mb-1 block text-xs font-bold text-slate-600">Internal notes</label><textarea name="admin_notes" rows="3" maxlength="5000" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Follow-up details, outcome, next action...">{{ $lead->admin_notes }}</textarea></div>
                        <button class="rounded-lg bg-orange-600 px-4 py-2 text-xs font-bold text-white hover:bg-orange-700">Save lead</button>
                    </form>
                </article>
            @empty
                <div class="p-10 text-center text-sm text-slate-500">No leads found. New website contact inquiries will appear here.</div>
            @endforelse
        </div>
        <div class="border-t border-slate-100 p-4">{{ $leads->links() }}</div>
    </section>
</div>
@endsection
