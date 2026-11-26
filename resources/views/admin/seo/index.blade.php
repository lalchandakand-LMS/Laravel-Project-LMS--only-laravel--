@extends('layouts.admin')
@section('title', 'SEO & Marketing')
@section('heading', 'SEO & Marketing Center')
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <section class="rounded-2xl border border-orange-200 bg-orange-50 p-5">
        <h2 class="font-bold text-orange-950">Search engine visibility</h2>
        <p class="mt-1 text-sm text-orange-900">Manage the public website's default search metadata and indexing policy. Page-specific course and blog metadata can override these defaults.</p>
    </section>

    <form method="POST" action="{{ route('admin.seo.update') }}" class="space-y-6">
        @csrf
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-bold text-slate-900">Global SEO defaults</h2>
            <div class="mt-4 space-y-4">
                <div>
                    <label for="seo_title" class="mb-1 block text-sm font-semibold text-slate-700">Default SEO title</label>
                    <input id="seo_title" name="seo_title" maxlength="120" value="{{ old('seo_title', $settings['seo_title'] ?? '') }}" placeholder="{{ setting('site_name', "Alom's English Center") }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Used when a page does not provide its own title.</p>
                </div>
                <div>
                    <label for="seo_description" class="mb-1 block text-sm font-semibold text-slate-700">Default meta description</label>
                    <textarea id="seo_description" name="seo_description" rows="3" maxlength="320" placeholder="Describe your English courses, coaching and learning experience." class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('seo_description', $settings['seo_description'] ?? '') }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Keep it useful and unique; search engines may choose a different snippet.</p>
                </div>
                <div>
                    <label for="seo_default_image" class="mb-1 block text-sm font-semibold text-slate-700">Default social sharing image URL</label>
                    <input id="seo_default_image" type="url" name="seo_default_image" value="{{ old('seo_default_image', $settings['seo_default_image'] ?? '') }}" placeholder="https://example.com/images/share-card.jpg" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label for="google_site_verification" class="mb-1 block text-sm font-semibold text-slate-700">Google Search Console verification token</label>
                    <input id="google_site_verification" name="google_site_verification" value="{{ old('google_site_verification', $settings['google_site_verification'] ?? '') }}" autocomplete="off" spellcheck="false" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Paste only the content token from Google's HTML meta-tag verification method.</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-bold text-slate-900">Indexing policy</h2>
            <label class="mt-4 flex items-start gap-3 rounded-xl border border-slate-200 p-4">
                <input type="checkbox" name="seo_robots_index" value="1" @checked(old('seo_robots_index', $settings['seo_robots_index'] ?? '1') === '1') class="mt-1 rounded border-slate-300 text-orange-600">
                <span><span class="block text-sm font-semibold text-slate-800">Allow public pages to be indexed</span><span class="mt-1 block text-xs text-slate-500">When disabled, robots.txt blocks crawling site-wide and public pages emit noindex. Admin and learner areas remain excluded from crawling either way.</span></span>
            </label>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <a href="{{ $robotsUrl }}" target="_blank" rel="noopener" class="rounded-xl border border-slate-200 p-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">View robots.txt ↗<span class="mt-1 block text-xs font-normal text-slate-500 break-all">{{ $robotsUrl }}</span></a>
                <a href="{{ $sitemapUrl }}" target="_blank" rel="noopener" class="rounded-xl border border-slate-200 p-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">View XML Sitemap ↗<span class="mt-1 block text-xs font-normal text-slate-500 break-all">{{ $sitemapUrl }}</span></a>
            </div>
        </section>

        <div class="flex justify-end"><button class="rounded-xl bg-orange-600 px-6 py-3 text-sm font-bold text-white hover:bg-orange-700">Save SEO Settings</button></div>
    </form>
</div>
@endsection
