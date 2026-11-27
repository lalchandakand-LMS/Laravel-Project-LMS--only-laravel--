@extends('layouts.admin')
@section('title', 'Google Performance Reports')
@section('heading', 'Google Search & Analytics Reports')
@section('content')
@php
    $gaHeaders = data_get($reports, 'analytics.metricHeaders', []);
    $gaValues = data_get($reports, 'analytics.rows.0.metricValues', []);
    $gaMetrics = [];
    foreach ($gaHeaders as $index => $header) {
        $gaMetrics[$header['name'] ?? 'metric_'.$index] = $gaValues[$index]['value'] ?? '0';
    }
    $searchRows = data_get($reports, 'search_console.rows', []);
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-600">Actual provider reports for {{ $startDate }} through {{ $endDate }}. Google API data is shown only when the authorized account can access the configured properties.</p>
        <a href="{{ route('admin.seo.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">SEO Settings</a>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800">{{ session('error') }}</div>@endif
    @if($error)<div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">{{ $error }}</div>@endif

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><h2 class="font-bold text-slate-900">Google account connection</h2><p class="mt-1 text-sm text-slate-500">Read-only OAuth scopes for Search Console and Google Analytics Data API.</p></div>
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $connected ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $connected ? 'Connected' : 'Not connected' }}</span>
        </div>
        @if(!$credentialsConfigured)
            <p class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">OAuth client credentials are not configured. Add <code>GOOGLE_CLIENT_ID</code>, <code>GOOGLE_CLIENT_SECRET</code> and the exact <code>GOOGLE_REDIRECT_URI</code> to the server environment, then follow <code>deployment/google-reporting.md</code>.</p>
        @elseif(!$connected)
            <div class="mt-4"><a href="{{ route('admin.seo.google.connect') }}" class="inline-flex rounded-lg bg-orange-600 px-5 py-3 text-sm font-bold text-white">Connect Google Account</a></div>
        @else
            <form method="POST" action="{{ route('admin.seo.google.disconnect') }}" class="mt-4" onsubmit="return confirm('Remove this Google reporting connection?')">
                @csrf @method('DELETE')
                <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-bold text-rose-700">Disconnect Google</button>
            </form>
        @endif
        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-500">Search Console property</p><p class="mt-1 break-all text-sm font-bold text-slate-800">{{ $siteProperty ?: 'Not configured' }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-500">GA4 property ID</p><p class="mt-1 text-sm font-bold text-slate-800">{{ $analyticsPropertyId ?: 'Not configured' }}</p></div>
        </div>
        @if(!$hasProperty)<p class="mt-4 text-sm text-amber-700">Set your Search Console property and numeric GA4 Property ID in SEO Settings to load reports.</p>@endif
    </section>

    @if($connected && $hasProperty && !$error)
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4"><h2 class="font-bold text-slate-900">Google Analytics 4</h2><p class="mt-1 text-xs text-slate-500">Live Google Analytics Data API response · last 28 complete days</p></div>
            @if($reports['analytics'])
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach(['totalUsers'=>'Total users','sessions'=>'Sessions','eventCount'=>'Events','totalRevenue'=>'Revenue'] as $key=>$label)
                        <article class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-black text-slate-900">{{ number_format((float) ($gaMetrics[$key] ?? 0), $key === 'totalRevenue' ? 2 : 0) }}</p></article>
                    @endforeach
                </div>
            @else
                <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">GA4 property ID is not configured. Add the numeric Property ID in SEO Settings.</p>
            @endif
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-5"><h2 class="font-bold text-slate-900">Search Console · Top queries</h2><p class="mt-1 text-xs text-slate-500">Top 10 query rows returned by the Search Analytics API for the selected period.</p></div>
            @if($reports['search_console'])
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Search query</th><th class="px-4 py-3">Clicks</th><th class="px-4 py-3">Impressions</th><th class="px-4 py-3">CTR</th><th class="px-4 py-3">Avg. position</th></tr></thead><tbody class="divide-y divide-slate-100">
                    @forelse($searchRows as $row)
                        <tr><td class="px-4 py-3 font-semibold text-slate-800">{{ $row['keys'][0] ?? '—' }}</td><td class="px-4 py-3">{{ number_format((int) ($row['clicks'] ?? 0)) }}</td><td class="px-4 py-3">{{ number_format((int) ($row['impressions'] ?? 0)) }}</td><td class="px-4 py-3">{{ number_format(((float) ($row['ctr'] ?? 0)) * 100, 2) }}%</td><td class="px-4 py-3">{{ number_format((float) ($row['position'] ?? 0), 1) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No query rows returned for this period.</td></tr>
                    @endforelse
                </tbody></table></div>
            @else
                <p class="p-5 text-sm text-slate-500">Search Console property is not configured. Add the verified property URL in SEO Settings.</p>
            @endif
        </section>
    @endif

    <p class="rounded-xl bg-amber-50 p-4 text-xs leading-5 text-amber-900">Reports use Google's read-only APIs and the access of the connected Google account. Search Console may omit low-volume queries; GA4 reports depend on the selected property's retention, consent and event setup. This dashboard does not invent impressions, clicks, users or revenue when an API is unavailable.</p>
</div>
@endsection
