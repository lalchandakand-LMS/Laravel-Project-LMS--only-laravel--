@extends('layouts.admin')
@section('title', 'SEO & Marketing')
@section('heading', 'SEO & Marketing Center')
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <section class="rounded-2xl border border-orange-200 bg-orange-50 p-5">
        <h2 class="font-bold text-orange-950">Search engine visibility</h2>
        <p class="mt-1 text-sm text-orange-900">Manage the public website's default search metadata and indexing policy. Page-specific course and blog metadata can override these defaults.</p>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 class="font-bold text-slate-900">On-page SEO audit</h2><p class="mt-1 text-sm text-slate-500">Checks published course and blog metadata and lists records that need attention.</p></div>
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ ($coursesMissingMeta + $postsMissingMeta) > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">{{ $coursesMissingMeta + $postsMissingMeta }} records need metadata</span>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-500">Published courses missing title or description</p><p class="mt-1 text-2xl font-black text-slate-900">{{ $coursesMissingMeta }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-500">Published blog posts missing title or description</p><p class="mt-1 text-2xl font-black text-slate-900">{{ $postsMissingMeta }}</p></div>
        </div>
        @if($missingMeta->isNotEmpty())
            <div class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200">
                @foreach($missingMeta as $item)
                    <div class="flex flex-wrap items-center justify-between gap-2 p-3"><div><span class="text-[10px] font-bold uppercase text-slate-400">{{ $item['type'] }}</span><p class="text-sm font-semibold text-slate-800">{{ $item['title'] }}</p></div><a href="{{ $item['url'] }}" class="text-xs font-bold text-orange-700 underline">Edit SEO fields →</a></div>
                @endforeach
            </div>
        @elseif(($coursesMissingMeta + $postsMissingMeta) === 0)
            <p class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">All published courses and blog posts have both SEO title and description fields filled.</p>
        @endif
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
                <div>
                    <label for="ga_measurement_id" class="mb-1 block text-sm font-semibold text-slate-700">Google Analytics 4 Measurement ID</label>
                    <input id="ga_measurement_id" name="ga_measurement_id" value="{{ old('ga_measurement_id', $settings['ga_measurement_id'] ?? '') }}" placeholder="G-XXXXXXXXXX" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                    <p class="mt-1 text-xs text-slate-500">GA4 loads only after a visitor accepts Analytics cookies.</p>
                </div>
                <div>
                    <label for="gtm_container_id" class="mb-1 block text-sm font-semibold text-slate-700">Google Tag Manager Container ID</label>
                    <input id="gtm_container_id" name="gtm_container_id" value="{{ old('gtm_container_id', $settings['gtm_container_id'] ?? '') }}" placeholder="GTM-XXXXXXX" pattern="GTM-[A-Za-z0-9]{4,12}" maxlength="16" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                    <p class="mt-1 text-xs text-slate-500">GTM loads only after Analytics or Marketing consent. Configure every tag in GTM to respect Google Consent Mode.</p>
                </div>
                <div>
                    <label for="meta_pixel_id" class="mb-1 block text-sm font-semibold text-slate-700">Meta / Facebook Pixel ID</label>
                    <input id="meta_pixel_id" name="meta_pixel_id" value="{{ old('meta_pixel_id', $settings['meta_pixel_id'] ?? '') }}" placeholder="123456789012345" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Meta Pixel loads only after a visitor accepts Marketing cookies.</p>
                    <p class="mt-2 text-xs text-amber-700">If Meta Pixel is already installed through GTM, leave the direct Meta Pixel ID empty to avoid duplicate events. If GA4 is configured in GTM, the standalone GA4 ID will not load.</p>
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
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-bold text-slate-900">Marketing workspace</h2>
        <p class="mt-1 text-sm text-slate-500">Manage campaign tracking links and follow up with website inquiries.</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ route('admin.marketing.campaigns.index') }}" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-bold text-white">Manage Campaigns</a>
            <a href="{{ route('admin.marketing.leads.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">View Leads</a>
        </div>
    </section>
</div>
@endsection
