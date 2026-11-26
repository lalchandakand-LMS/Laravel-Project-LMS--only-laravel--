@extends('layouts.admin')
@section('title', $redirect->exists ? 'Edit SEO Redirect' : 'Create SEO Redirect')
@section('heading', $redirect->exists ? 'Edit SEO Redirect' : 'Create SEO Redirect')
@section('content')
<div class="mx-auto max-w-3xl">
    <form method="POST" action="{{ $redirect->exists ? route('admin.seo.redirects.update', $redirect) : route('admin.seo.redirects.store') }}" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @if($redirect->exists) @method('PUT') @endif
        <div>
            <label for="source_path" class="mb-1 block text-sm font-semibold text-slate-700">Old URL path</label>
            <input id="source_path" name="source_path" required maxlength="255" value="{{ old('source_path', $redirect->source_path) }}" placeholder="/old-course-slug" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            <p class="mt-1 text-xs text-slate-500">Path only, beginning with /. Do not include the domain, query string or fragment.</p>
        </div>
        <div>
            <label for="destination_path" class="mb-1 block text-sm font-semibold text-slate-700">New internal destination path</label>
            <input id="destination_path" name="destination_path" required maxlength="2048" value="{{ old('destination_path', $redirect->destination_path) }}" placeholder="/courses/spoken-english" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            <p class="mt-1 text-xs text-slate-500">Must be a different same-site path. External URLs and protocol-relative links are blocked to prevent open redirects.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="status_code" class="mb-1 block text-sm font-semibold text-slate-700">Redirect type</label><select id="status_code" name="status_code" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option value="301" @selected((int) old('status_code', $redirect->status_code) === 301)>301 — Permanent</option><option value="302" @selected((int) old('status_code', $redirect->status_code) === 302)>302 — Temporary</option></select></div>
            <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-semibold text-slate-700"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $redirect->is_active ?? true)) class="rounded border-slate-300 text-orange-600"> Enable this redirect</label>
        </div>
        <div class="flex flex-wrap justify-end gap-3"><a href="{{ route('admin.seo.redirects.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700">Cancel</a><button class="rounded-lg bg-orange-600 px-5 py-2.5 text-sm font-bold text-white">Save Redirect</button></div>
    </form>
</div>
@endsection
