@extends('layouts.admin')
@section('title', 'Marketing Popup')
@section('heading', 'Marketing Popup & Lead Capture')
@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5"><h2 class="text-lg font-black text-slate-900">Popup campaign settings</h2><p class="mt-1 text-sm text-slate-500">Collect interested visitors as marketing leads. Frequency controls prevent repeated interruptions.</p></div>
        <form method="POST" action="{{ route('admin.marketing.popup.update') }}" class="space-y-5">
            @csrf
            <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['marketing_popup_enabled'] ?? '0') === '1') class="rounded border-slate-300 text-orange-600"><span><b class="block text-sm text-slate-900">Enable website popup</b><small class="text-xs text-slate-500">Only public pages are eligible; authenticated and checkout pages never show it.</small></span></label>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="mb-1 block text-sm font-semibold">Popup title</label><input name="title" required maxlength="120" value="{{ old('title', $settings['marketing_popup_title'] ?? 'Get course information') }}" class="w-full rounded-lg border-slate-300 text-sm">@error('title')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-1 block text-sm font-semibold">Button text</label><input name="button_text" required maxlength="50" value="{{ old('button_text', $settings['marketing_popup_button_text'] ?? 'Request information') }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
            </div>
            <div><label class="mb-1 block text-sm font-semibold">Message</label><textarea name="body" required maxlength="500" rows="3" class="w-full rounded-lg border-slate-300 text-sm">{{ old('body', $settings['marketing_popup_body'] ?? 'Leave your details and our team will help you choose the right course.') }}</textarea></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="mb-1 block text-sm font-semibold">Show after (seconds)</label><input type="number" name="delay" min="0" max="60" required value="{{ old('delay', $settings['marketing_popup_delay'] ?? '8') }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
                <div><label class="mb-1 block text-sm font-semibold">Frequency cap</label><select name="frequency" class="w-full rounded-lg border-slate-300 text-sm">@foreach(['session'=>'Once per browser session','day'=>'Once per day','week'=>'Once per 7 days'] as $value=>$label)<option value="{{ $value }}" @selected(old('frequency', $settings['marketing_popup_frequency'] ?? 'week') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="mb-1 block text-sm font-semibold">Display on</label><select name="display" class="w-full rounded-lg border-slate-300 text-sm"><option value="home" @selected(old('display', $settings['marketing_popup_display'] ?? 'home') === 'home')>Homepage only</option><option value="public" @selected(old('display', $settings['marketing_popup_display'] ?? 'home') === 'public')>All public pages</option></select></div>
                <div><label class="mb-1 block text-sm font-semibold">After successful submit, go to (optional)</label><input name="destination_url" maxlength="2048" value="{{ old('destination_url', $settings['marketing_popup_destination_url'] ?? '') }}" placeholder="/courses" class="w-full rounded-lg border-slate-300 text-sm"><p class="mt-1 text-xs text-slate-500">Same-site path only. Leave blank to remain on the current page.</p>@error('destination_url')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
            </div>
            <div class="rounded-xl bg-amber-50 p-4 text-xs leading-5 text-amber-900">Consent is required before contact details are saved. Submissions are added to Marketing Leads with UTM attribution where available. The popup is disabled by default.</div>
            <div class="flex justify-end"><button class="rounded-lg bg-orange-600 px-5 py-3 text-sm font-bold text-white">Save Popup Settings</button></div>
        </form>
    </section>
</div>
@endsection
