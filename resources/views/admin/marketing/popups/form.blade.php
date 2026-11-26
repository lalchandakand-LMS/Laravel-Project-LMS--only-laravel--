@extends('layouts.admin')
@section('title', $popup->exists ? 'Edit Popup' : 'Create Popup')
@section('heading', $popup->exists ? 'Edit Marketing Popup' : 'Create Marketing Popup')
@section('content')
<div class="mx-auto max-w-4xl">
    <form method="POST" action="{{ $popup->exists ? route('admin.marketing.popups.update',$popup) : route('admin.marketing.popups.store') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf @if($popup->exists) @method('PUT') @endif
        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2"><label class="mb-1 block text-sm font-semibold">Popup title</label><input name="title" required maxlength="160" value="{{ old('title',$popup->title) }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
            <div class="md:col-span-2"><label class="mb-1 block text-sm font-semibold">Message</label><textarea name="body" rows="4" maxlength="3000" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Write a short offer or admission message...">{{ old('body',$popup->body) }}</textarea><p class="mt-1 text-xs text-slate-500">Plain text only. HTML and scripts are not accepted.</p></div>
            <div class="md:col-span-2"><label class="mb-1 block text-sm font-semibold">Image URL (optional)</label><input name="image_url" maxlength="2048" value="{{ old('image_url',$popup->image_url) }}" placeholder="/storage/media/offer.jpg or https://..." class="w-full rounded-lg border-slate-300 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Button label</label><input name="cta_label" maxlength="60" value="{{ old('cta_label',$popup->cta_label) }}" placeholder="Explore courses" class="w-full rounded-lg border-slate-300 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Button URL</label><input name="cta_url" maxlength="2048" value="{{ old('cta_url',$popup->cta_url) }}" placeholder="/courses" class="w-full rounded-lg border-slate-300 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Status</label><select name="status" class="w-full rounded-lg border-slate-300 text-sm">@foreach(\App\Models\MarketingPopup::STATUSES as $status)<option value="{{ $status }}" @selected(old('status',$popup->status)===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm font-semibold">Audience</label><select name="target_audience" class="w-full rounded-lg border-slate-300 text-sm">@foreach(\App\Models\MarketingPopup::AUDIENCES as $audience)<option value="{{ $audience }}" @selected(old('target_audience',$popup->target_audience)===$audience)>{{ ucfirst($audience) }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm font-semibold">Display location</label><select name="display_on" class="w-full rounded-lg border-slate-300 text-sm"><option value="all_public_pages" @selected(old('display_on',$popup->display_on)==='all_public_pages')>All public pages</option><option value="homepage_only" @selected(old('display_on',$popup->display_on)==='homepage_only')>Homepage only</option></select></div>
            <div><label class="mb-1 block text-sm font-semibold">Frequency</label><select name="frequency" class="w-full rounded-lg border-slate-300 text-sm"><option value="once_per_session" @selected(old('frequency',$popup->frequency)==='once_per_session')>Once per browser session</option><option value="every_page" @selected(old('frequency',$popup->frequency)==='every_page')>Every eligible page</option></select></div>
            <div><label class="mb-1 block text-sm font-semibold">Priority (higher appears first)</label><input type="number" name="priority" min="0" max="10000" value="{{ old('priority',$popup->priority ?? 0) }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Start date/time</label><input type="datetime-local" name="starts_at" value="{{ old('starts_at',$popup->starts_at?->format('Y-m-d\\TH:i')) }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">End date/time</label><input type="datetime-local" name="ends_at" value="{{ old('ends_at',$popup->ends_at?->format('Y-m-d\\TH:i')) }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
        </div>
        <div class="rounded-xl bg-amber-50 p-3 text-xs text-amber-900">Only one highest-priority active popup is displayed per eligible public page. Impression and click counts are recorded only when the visitor has accepted Analytics cookies.</div>
        <div class="flex justify-end gap-2"><a href="{{ route('admin.marketing.popups.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</a><button class="rounded-lg bg-orange-600 px-5 py-2 text-sm font-bold text-white">Save Popup</button></div>
    </form>
</div>
@endsection
