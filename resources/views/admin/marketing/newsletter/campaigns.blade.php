@extends('layouts.admin')
@section('title', 'Newsletter Campaigns')
@section('heading', 'Newsletter Campaigns')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-600">Create plain-text email campaigns for subscribers who have confirmed their consent.</p>
        <a href="{{ route('admin.marketing.newsletter.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Manage Subscribers</a>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-4"><p class="text-xs font-semibold text-emerald-800">Confirmed recipients available</p><p class="mt-1 text-3xl font-black text-emerald-950">{{ number_format($subscribedCount) }}</p><p class="mt-1 text-xs text-emerald-800">Pending and unsubscribed addresses are excluded.</p></article>
        <article class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold text-slate-500">Campaigns created</p><p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($campaigns->total()) }}</p><p class="mt-1 text-xs text-slate-500">Delivery counts update as queued jobs finish.</p></article>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-black text-slate-900">Compose a newsletter</h2>
        <p class="mt-1 text-sm text-slate-500">A separate unsubscribe link is included for each confirmed subscriber. Emails are sent as plain text.</p>
        <form method="POST" action="{{ route('admin.marketing.newsletter.campaigns.store') }}" class="mt-5 space-y-4">
            @csrf
            <div><label for="subject" class="mb-1 block text-sm font-semibold text-slate-700">Email subject</label><input id="subject" name="subject" required maxlength="180" value="{{ old('subject') }}" class="w-full rounded-lg border-slate-300 text-sm">@error('subject')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
            <div><label for="title" class="mb-1 block text-sm font-semibold text-slate-700">Newsletter heading</label><input id="title" name="title" required maxlength="180" value="{{ old('title') }}" class="w-full rounded-lg border-slate-300 text-sm">@error('title')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
            <div><label for="body" class="mb-1 block text-sm font-semibold text-slate-700">Message</label><textarea id="body" name="body" rows="8" maxlength="12000" required class="w-full rounded-lg border-slate-300 text-sm">{{ old('body') }}</textarea><p class="mt-1 text-xs text-slate-500">Plain text only. Do not include private student information.</p>@error('body')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
            <label class="flex items-start gap-2 rounded-xl bg-amber-50 p-4 text-xs leading-5 text-amber-900"><input type="checkbox" name="confirm_send" value="1" required class="mt-1 rounded border-amber-400"> I confirm this message is relevant to the subscribers and should be sent to all currently confirmed addresses ({{ number_format($subscribedCount) }} recipients).</label>
            @error('confirm_send')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
            @error('queue')<p class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ $message }}</p>@enderror
            <div class="flex justify-end"><button @disabled($subscribedCount === 0) class="rounded-lg bg-orange-600 px-5 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">Queue Newsletter Campaign</button></div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-4"><h2 class="font-bold text-slate-900">Campaign delivery history</h2><p class="mt-1 text-xs text-slate-500">A campaign is complete when all snapshotted recipients are sent, skipped or failed.</p></div>
        <div class="divide-y divide-slate-100">
            @forelse($campaigns as $campaign)
                <article class="p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0"><p class="font-bold text-slate-900">{{ $campaign->title }}</p><p class="mt-1 text-xs text-slate-500">{{ $campaign->subject }} · {{ $campaign->queued_at?->format('d M Y, h:i A') ?: $campaign->created_at->format('d M Y') }}</p></div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ str_replace('_', ' ', ucfirst($campaign->status)) }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-lg bg-slate-50 p-3"><p class="text-[10px] text-slate-500">Recipients</p><p class="font-black text-slate-900">{{ number_format($campaign->recipients_count) }}</p></div>
                        <div class="rounded-lg bg-emerald-50 p-3"><p class="text-[10px] text-emerald-700">Sent</p><p class="font-black text-emerald-900">{{ number_format($campaign->sent_count) }}</p></div>
                        <div class="rounded-lg bg-rose-50 p-3"><p class="text-[10px] text-rose-700">Failed</p><p class="font-black text-rose-900">{{ number_format($campaign->failed_count) }}</p></div>
                        <div class="rounded-lg bg-amber-50 p-3"><p class="text-[10px] text-amber-700">Skipped</p><p class="font-black text-amber-900">{{ number_format($campaign->skipped_count) }}</p></div>
                    </div>
                </article>
            @empty
                <p class="p-8 text-center text-sm text-slate-500">No newsletter campaigns have been created yet.</p>
            @endforelse
        </div>
        <div class="border-t border-slate-100 p-4">{{ $campaigns->links() }}</div>
    </section>

    <p class="rounded-xl bg-amber-50 p-4 text-xs leading-5 text-amber-900">Delivery uses Laravel's database queue. Before sending from production, configure SMTP and keep a queue worker running for the <code>notifications</code> queue. The app blocks bulk sending when the production queue connection is set to synchronous mode. SMTP delivery and provider-side open/click analytics are not claimed by this dashboard.</p>
</div>
@endsection
