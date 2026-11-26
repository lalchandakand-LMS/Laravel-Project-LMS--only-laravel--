<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Course;
use App\Models\Post;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    public function index()
    {
        $missingMeta = collect();

        $coursesMissingMeta = Course::query()->where('is_published', true)
            ->where(function ($query) {
                $query->whereNull('meta_title')->orWhere('meta_title', '')
                    ->orWhereNull('meta_description')->orWhere('meta_description', '');
            })->count();

        $postsMissingMeta = Post::published()
            ->where(function ($query) {
                $query->whereNull('meta_title')->orWhere('meta_title', '')
                    ->orWhereNull('meta_description')->orWhere('meta_description', '');
            })->count();

        Course::query()->where('is_published', true)
            ->where(function ($query) {
                $query->whereNull('meta_title')->orWhere('meta_title', '')
                    ->orWhereNull('meta_description')->orWhere('meta_description', '');
            })->orderBy('title')->limit(8)->get(['id', 'title', 'slug'])->each(function ($course) use ($missingMeta) {
                $missingMeta->push(['type' => 'Course', 'title' => $course->title, 'url' => route('admin.courses.edit', ['course' => $course->slug])]);
            });

        Post::published()
            ->where(function ($query) {
                $query->whereNull('meta_title')->orWhere('meta_title', '')
                    ->orWhereNull('meta_description')->orWhere('meta_description', '');
            })->orderBy('title')->limit(8)->get(['id', 'title', 'slug'])->each(function ($post) use ($missingMeta) {
                $missingMeta->push(['type' => 'Blog post', 'title' => $post->title, 'url' => route('admin.posts.edit', ['post' => $post->slug])]);
            });

        return view('admin.seo.index', [
            'settings' => Setting::all_(),
            'sitemapUrl' => route('sitemap'),
            'robotsUrl' => route('robots'),
            'coursesMissingMeta' => $coursesMissingMeta,
            'postsMissingMeta' => $postsMissingMeta,
            'missingMeta' => $missingMeta,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'seo_default_image' => ['nullable', 'url:http,https', 'max:2048'],
            'google_site_verification' => ['nullable', 'regex:/^[A-Za-z0-9_-]{0,255}$/'],
            'ga4_measurement_id' => ['nullable', 'regex:/^G-[A-Z0-9]{4,20}$/'],
            'gtm_container_id' => ['nullable', 'regex:/^GTM-[A-Z0-9]{4,12}$/'],
            'meta_pixel_id' => ['nullable', 'regex:/^[0-9]{6,20}$/'],
            'seo_robots_index' => ['nullable', 'boolean'],
        ]);

        foreach (['seo_title', 'seo_description', 'seo_default_image', 'google_site_verification', 'ga4_measurement_id', 'gtm_container_id', 'meta_pixel_id'] as $key) {
            Setting::set($key, trim((string) ($validated[$key] ?? '')));
        }

        Setting::set('seo_robots_index', $request->boolean('seo_robots_index') ? '1' : '0');

        return redirect()->route('admin.seo.index')->with('success', 'SEO settings saved.');
    }

    public function robots()
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /my/',
            'Disallow: /teacher/',
            'Disallow: /guardian/',
            'Disallow: /branch/',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /messages',
            'Disallow: /notifications',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /reset-password',
        ];

        if (Setting::get('seo_robots_index', '1') !== '1') {
            $lines[] = 'Disallow: /';
        } else {
            $lines[] = 'Allow: /';
            $lines[] = 'Sitemap: '.route('sitemap');
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Robots-Tag' => Setting::get('seo_robots_index', '1') === '1' ? 'all' : 'noindex, nofollow',
        ]);
    }
}
