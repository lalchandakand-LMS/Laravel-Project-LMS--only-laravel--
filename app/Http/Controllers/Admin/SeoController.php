<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Setting;
use Illuminate\\Http\\Request;

class SeoController extends Controller
{
    public function index()
    {
        return view('admin.seo.index', [
            'settings' => Setting::all_(),
            'sitemapUrl' => route('sitemap'),
            'robotsUrl' => route('robots'),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'seo_default_image' => ['nullable', 'url:http,https', 'max:2048'],
            'google_site_verification' => ['nullable', 'regex:/^[A-Za-z0-9_-]{0,255}$/'],
            'seo_robots_index' => ['nullable', 'boolean'],
        ]);

        foreach (['seo_title', 'seo_description', 'seo_default_image', 'google_site_verification'] as $key) {
            Setting::set($key, trim((string) ($validated[$key] ?? '')));
        }

        Setting::set('seo_robots_index', $request->boolean('seo_robots_index') ? '1' : '0');

        return redirect()->route('admin.seo.index')->with('success', 'SEO settings saved.');
    }

    public function robots()
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin/',
            'Disallow: /my/',
            'Disallow: /teacher/',
            'Disallow: /guardian/',
            'Disallow: /branch/',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /messages',
            'Disallow: /notifications',
        ];

        if (Setting::get('seo_robots_index', '1') !== '1') {
            $lines[] = 'Disallow: /';
        } else {
            $lines[] = 'Allow: /';
            $lines[] = 'Sitemap: '.route('sitemap');
        }

        return response(implode("\\n", $lines)."\\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Robots-Tag' => Setting::get('seo_robots_index', '1') === '1' ? 'all' : 'noindex, nofollow',
        ]);
    }
}
