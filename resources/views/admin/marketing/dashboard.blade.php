@extends('layouts.admin')
@section('title', 'Marketing Dashboard')
@section('heading', 'Marketing Performance Dashboard')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><p class="text-sm text-slate-600">Lead and campaign performance from website inquiries with recorded UTM attribution.</p><p class="mt-1 text-xs text-slate-500">Reporting period: {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</p></div>
        <div class="flex gap-2">
            <a href="{{ route('admin.marketing.campaigns.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700">Campaigns</a>
            <a href="{{ route('admin.marketing.leads.index') }}" class="rounded-lg bg-orange-600 px-3 py-2 text-xs font-bold text-white">Manage Leads</a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.marketing.dashboard') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <div><label for="from" class="mb-1 block text-xs font-semibold text-slate-600">From</label><input id="from" type="date" name="from" value="{{ request('from', $from->toDateString()) }}" required class="rounded-lg border-slate-300 text-sm"></div>
        <div><label for="to" class="mb-1 block text-xs font-semibold text-slate-600">To</label><input id="to" type="date" name="to" value="{{ request('to', $to->toDateString()) }}" required class="rounded-lg border-slate-300 text-sm"></div>
        <button class="rounded-lg bg-orange-600 px-4 py-2.5 text-xs font-bold text-white">Apply period</button>
        <a href="{{ route('admin.marketing.dashboard') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-bold text-slate-700">Last 30 days</a>
        @error('from')<p class="w-full text-xs text-rose-600">{{ $message }}</p>@enderror
        @error('to')<p class="w-full text-xs text-rose-600">{{ $message }}</p>@enderror
    </form>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold text-slate-500">Total captured leads</p><p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($totalLeads) }}</p><p class="mt-1 text-xs text-slate-500">Website inquiries in selected period</p></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold text-slate-500">New leads</p><p class="mt-2 text-3xl font-black text-blue-700">{{ number_format($newLeads) }}</p><p class="mt-1 text-xs text-slate-500">Awaiting first follow-up</p></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold text-slate-500">Converted leads</p><p class="mt-2 text-3xl font-black text-emerald-700">{{ number_format($convertedLeads) }}</p><p class="mt-1 text-xs text-slate-500">Marked converted by an admin</p></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold text-slate-500">Lead conversion rate</p><p class="mt-2 text-3xl font-black text-orange-700">{{ number_format($conversionRate, 1) }}%</p><p class="mt-1 text-xs text-slate-500">Converted leads ÷ captured leads</p></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold text-slate-500">Attributed orders</p><p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($attributedOrderCount) }}</p><p class="mt-1 text-xs text-slate-500">{{ $campaignCount }} campaigns · {{ $activeCampaignCount }} active</p></article>
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm"><p class="text-xs font-semibold text-emerald-700">Verified campaign collections</p><p class="mt-2 text-3xl font-black text-emerald-900">৳{{ number_format((float) $verifiedRevenue, 2) }}</p><p class="mt-1 text-xs text-emerald-700">Verified payments on attributed orders</p></article>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2"><div><h2 class="font-bold text-slate-900">New leads · selected period</h2><p class="text-xs text-slate-500">Daily contact inquiries captured by the LMS for the selected date range.</p></div><span class="text-xs text-slate-500">{{ $dailyLeads->count() }} days</span></div>
        <div class="mt-5 grid grid-cols-7 gap-2 sm:grid-cols-7 lg:grid-cols-14">
            @foreach($dailyLeads as $day)
                <div class="min-w-0 text-center">
                    <div class="flex h-28 items-end justify-center rounded-lg bg-slate-50 px-1 pb-1" title="{{ $day['count'] }} leads">
                        <div class="w-full rounded-t bg-orange-500" style="height: {{ $day['count'] > 0 ? max(8, min(100, $day['count'] * 12)) : 2 }}%"></div>
                    </div>
                    <p class="mt-2 text-[9px] text-slate-500">{{ $day['date'] }}</p>
                    <p class="text-xs font-bold text-slate-700">{{ $day['count'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-4"><h2 class="font-bold text-slate-900">Campaign performance</h2><p class="text-xs text-slate-500">Leads attributed to each saved campaign.</p></div>
            <div class="divide-y divide-slate-100">
                @forelse($campaigns as $campaign)
                    <div class="flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">{{ $campaign->name }}</p><p class="mt-1 text-xs text-slate-500">{{ ucfirst($campaign->channel) }} · {{ $campaign->status }} · {{ $campaign->converted_leads_count }} converted · {{ $campaign->orders_count }} orders</p></div>
                        <a href="{{ route('admin.marketing.leads.index', ['campaign' => $campaign->slug]) }}" class="shrink-0 text-sm font-black text-orange-700 underline">{{ $campaign->leads_count }} leads <span class="block text-right text-xs font-bold text-emerald-700">৳{{ number_format((float) $campaign->verified_revenue, 2) }}</span></a>
                    </div>
                @empty
                    <p class="p-6 text-sm text-slate-500">No campaigns have been created yet.</p>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-4"><h2 class="font-bold text-slate-900">Top lead sources</h2><p class="text-xs text-slate-500">Based on UTM source values captured from inquiry sessions.</p></div>
            <div class="divide-y divide-slate-100">
                @forelse($sources as $source)
                    <div class="flex items-center justify-between gap-3 p-4"><span class="text-sm font-semibold text-slate-800">{{ $source->source }}</span><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ $source->leads_count }} leads</span></div>
                @empty
                    <p class="p-6 text-sm text-slate-500">No UTM-attributed leads yet. Use campaign tracking links to begin attribution.</p>
                @endforelse
            </div>
        </section>
    </div>
    <p class="rounded-xl bg-amber-50 p-4 text-xs leading-5 text-amber-900">Reporting scope: lead counts and admin-marked conversions come from the LMS database. Revenue is the sum of verified payment ledger entries on orders with a captured UTM campaign; it excludes unattributed orders and does not represent ad spend or provider-reported ad conversions.</p>
</div>
@endsection
