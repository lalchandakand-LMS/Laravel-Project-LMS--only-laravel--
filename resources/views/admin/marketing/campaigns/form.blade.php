@extends('layouts.admin')
@section('title', $campaign->exists ? 'Edit Campaign' : 'Create Campaign')
@section('heading', $campaign->exists ? 'Edit Marketing Campaign' : 'Create Marketing Campaign')
@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <form method="POST" action="{{ $campaign->exists ? route('admin.marketing.campaigns.update',$campaign) : route('admin.marketing.campaigns.store') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf @if($campaign->exists) @method('PUT') @endif
        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2"><label class="mb-1 block text-sm font-semibold">Campaign name</label><input name="name" required maxlength="160" value="{{ old('name',$campaign->name) }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Channel</label><select name="channel" class="w-full rounded-lg border-slate-300 text-sm">@foreach($channels as $channel)<option value="{{ $channel }}" @selected(old('channel',$campaign->channel)===$channel)>{{ ucfirst($channel) }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm font-semibold">Status</label><select name="status" class="w-full rounded-lg border-slate-300 text-sm">@foreach($statuses as $status)<option value="{{ $status }}" @selected(old('status',$campaign->status)===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm font-semibold">UTM source</label><input name="source" required maxlength="120" value="{{ old('source',$campaign->source) }}" placeholder="facebook" class="w-full rounded-lg border-slate-300 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">UTM medium</label><input name="medium" required maxlength="120" value="{{ old('medium',$campaign->medium) }}" placeholder="paid_social" class="w-full rounded-lg border-slate-300 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">UTM term (optional)</label><input name="term" maxlength="160" value="{{ old('term',$campaign->term) }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">UTM content (optional)</label><input name="content" maxlength="160" value="{{ old('content',$campaign->content) }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
            <div class="md:col-span-2"><label class="mb-1 block text-sm font-semibold">Destination URL</label><input name="destination_url" required maxlength="2048" value="{{ old('destination_url',$campaign->destination_url) }}" placeholder="/courses or https://example.com/landing-page" class="w-full rounded-lg border-slate-300 text-sm"><p class="mt-1 text-xs text-slate-500">Use a same-site path or HTTP/HTTPS URL. javascript:, data:, protocol-relative and credential-bearing URLs are rejected.</p></div>
            <div><label class="mb-1 block text-sm font-semibold">Budget (৳, optional)</label><input type="number" name="budget" min="0" step="0.01" value="{{ old('budget',$campaign->budget) }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Start date</label><input type="datetime-local" name="starts_at" value="{{ old('starts_at',$campaign->starts_at?->format('Y-m-d\\TH:i')) }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">End date</label><input type="datetime-local" name="ends_at" value="{{ old('ends_at',$campaign->ends_at?->format('Y-m-d\\TH:i')) }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
        </div>
        <div class="flex justify-end gap-2"><a href="{{ route('admin.marketing.campaigns.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</a><button class="rounded-lg bg-orange-600 px-5 py-2 text-sm font-bold text-white">Save Campaign</button></div>
    </form>
    @if($campaign->exists)<section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"><h2 class="font-bold text-emerald-900">Tracking URL</h2><p class="mt-2 break-all text-sm text-emerald-800">{{ $campaign->trackingUrl() }}</p></section>@endif
</div>
@endsection
