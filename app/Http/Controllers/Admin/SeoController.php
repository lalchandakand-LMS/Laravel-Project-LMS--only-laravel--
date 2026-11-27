<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Course;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SeoController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query('export') === 'csv') {
            return $this->exportContentAudit();
        }

        $missingMeta = collect();
        $contentScores = collect();

        Course::query()->where('is_published', true)
            ->get(['id', 'title', 'slug', 'subtitle', 'description', 'thumbnail', 'meta_title', 'meta_description'])
            ->each(fn ($item) => $contentScores->push($this->scoreContent('Course', $item)));

        Post::published()
            ->get(['id', 'title', 'slug', 'excerpt', 'content', 'thumbnail', 'meta_title', 'meta_description'])
            ->each(fn ($item) => $contentScores->push($this->scoreContent('Blog post', $item)));

        $coursesMissingMeta = Course::query()->where('is_published', true)
            ->where(fn ($query) => $query->whereNull('meta_title')->orWhere('meta_title', '')
                ->orWhereNull('meta_description')->orWhere('meta_description', ''))
            ->count();

        $postsMissingMeta = Post::published()
            ->where(fn ($query) => $query->whereNull('meta_title')->orWhere('meta_title', '')
                ->orWhereNull('meta_description')->orWhere('meta_description', ''))
            ->count();

        Course::query()->where('is_published', true)
            ->where(fn ($query) => $query->whereNull('meta_title')->orWhere('meta_title', '')
                ->orWhereNull('meta_description')->orWhere('meta_description', ''))
            ->orderBy('title')->limit(8)->get(['id', 'title', 'slug'])
            ->each(fn ($course) => $missingMeta->push([
                'type' => 'Course', 'title' => $course->title,
                'url' => route('admin.courses.edit', ['course' => $course->slug]),
            ]));

        Post::published()
            ->where(fn ($query) => $query->whereNull('meta_title')->orWhere('meta_title', '')
                ->orWhereNull('meta_description')->orWhere('meta_description', ''))
            ->orderBy('title')->limit(8)->get(['id', 'title', 'slug'])
            ->each(fn ($post) => $missingMeta->push([
                'type' => 'Blog post', 'title' => $post->title,
                'url' => route('admin.posts.edit', ['post' => $post->slug]),
            ]));

        return view('admin.seo.index', [
            'settings' => Setting::all_(),
            'sitemapUrl' => route('sitemap'),
            'robotsUrl' => route('robots'),
            'coursesMissingMeta' => $coursesMissingMeta,
            'postsMissingMeta' => $postsMissingMeta,
            'missingMeta' => $missingMeta,
            'contentScores' => $contentScores->sortBy('score')->values(),
        ]);
    }

    private function exportContentAudit()
    {
        $filename = 'seo-content-audit-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            // UTF-8 BOM keeps Bangla titles readable in common spreadsheet apps.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Content type', 'Title', 'Edit URL', 'SEO score', 'Passed checks', 'Failed checks']);

            Course::query()->where('is_published', true)
                ->orderBy('id')
                ->cursor()
                ->each(function ($item) use ($output) {
                    $this->writeAuditRow($output, 'Course', $item);
                });

            Post::published()->orderBy('id')->cursor()
                ->each(fn ($item) => $this->writeAuditRow($output, 'Blog post', $item));

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function writeAuditRow($output, string $type, object $item): void
    {
        $audit = $this->scoreContent($type, $item);
        $passed = collect($audit['checks'])->where('passed', true)->pluck('label')->implode('; ');
        $failed = collect($audit['checks'])->where('passed', false)->pluck('label')->implode('; ');

        fputcsv($output, [
            $type,
            $item->title,
            $audit['url'],
            $audit['score'],
            $passed,
            $failed,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'seo_default_image' => ['nullable', 'url:http,https', 'max:2048'],
            'google_site_verification' => ['nullable', 'regex:/^[A-Za-z0-9_-]{0,255}$/'],
            'google_search_console_property' => ['nullable', 'string', 'max:255'],
            'google_analytics_property_id' => ['nullable', 'regex:/^[0-9]{1,20}$/'],
            'ga_measurement_id' => ['nullable', 'regex:/^G-[A-Z0-9]{6,20}$/i'],
            'meta_pixel_id' => ['nullable', 'regex:/^[0-9]{5,20}$/'],
            'gtm_container_id' => ['nullable', 'regex:/^GTM-[A-Z0-9]{4,12}$/i'],
            'seo_robots_index' => ['nullable', 'boolean'],
            'seo_robots_custom_rules' => ['nullable', 'string', 'max:4000'],
        ]);

        $customRules = $this->validatedRobotsRules((string) ($validated['seo_robots_custom_rules'] ?? ''));
        $searchConsoleProperty = trim((string) ($validated['google_search_console_property'] ?? ''));
        if ($searchConsoleProperty !== '' && ! preg_match('#^(https://[A-Za-z0-9.-]+/?|sc-domain:[A-Za-z0-9.-]+)$#i', $searchConsoleProperty)) {
            throw ValidationException::withMessages(['google_search_console_property' => 'Use an HTTPS URL-prefix property or a valid sc-domain:example.com property.']);
        }

        foreach (['seo_title', 'seo_description', 'seo_default_image', 'google_site_verification', 'google_search_console_property', 'google_analytics_property_id', 'ga_measurement_id', 'meta_pixel_id', 'gtm_container_id'] as $key) {
            Setting::set($key, trim((string) ($validated[$key] ?? '')));
        }

        Setting::set('seo_robots_index', $request->boolean('seo_robots_index') ? '1' : '0');
        Setting::set('seo_robots_custom_rules', $customRules);

        return redirect()->route('admin.seo.index')->with('success', 'SEO settings saved.');
    }

    public function robots()
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /my/',
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
            $customRules = $this->validatedRobotsRules((string) Setting::get('seo_robots_custom_rules', ''));
            if ($customRules !== '') {
                $lines = array_merge($lines, preg_split('/\r\n|\r|\n/', $customRules));
            }
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Robots-Tag' => Setting::get('seo_robots_index', '1') === '1' ? 'all' : 'noindex, nofollow',
        ]);
    }

    private function validatedRobotsRules(string $rules): string
    {
        $safe = [];
        foreach (preg_split('/\r\n|\r|\n/', $rules) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! preg_match('/^(Allow|Disallow):\s*(\/[A-Za-z0-9._~*\/-]*)$/i', $line, $matches)) {
                throw ValidationException::withMessages([
                    'seo_robots_custom_rules' => 'Use only Allow: /path or Disallow: /path rules, one per line. User-agent and Sitemap are managed automatically.',
                ]);
            }

            $path = $matches[2];
            if (str_contains($path, '..')
                || preg_match('#^/(admin|my|teacher|guardian|branch|cart|checkout|messages|notifications)(/|$)#i', $path)
                || $path === '/') {
                throw ValidationException::withMessages([
                    'seo_robots_custom_rules' => 'Custom rules cannot override protected account, checkout, or site-wide indexing paths.',
                ]);
            }

            $safe[] = ucfirst(strtolower($matches[1])).': '.$path;
        }

        return implode("\n", array_unique($safe));
    }

    private function scoreContent(string $type, object $item): array
    {
        $title = trim((string) ($item->meta_title ?: $item->title));
        $description = trim((string) ($item->meta_description ?: ($item->excerpt ?? $item->subtitle ?? '')));
        $body = trim(strip_tags((string) ($item->content ?? $item->description ?? '')));
        $wordCount = preg_match_all('/[\p{L}\p{N}]+/u', $body) ?: 0;
        $checks = [
            ['label' => 'SEO title present', 'passed' => $title !== '', 'points' => 20],
            ['label' => 'Meta description present', 'passed' => $description !== '', 'points' => 20],
            ['label' => 'Title length 30–60 characters', 'passed' => mb_strlen($title) >= 30 && mb_strlen($title) <= 60, 'points' => 10],
            ['label' => 'Description length 120–160 characters', 'passed' => mb_strlen($description) >= 120 && mb_strlen($description) <= 160, 'points' => 10],
            ['label' => 'Readable URL slug', 'passed' => filled($item->slug) && mb_strlen($item->slug) <= 75 && ! preg_match('/[-_]{2,}/', $item->slug), 'points' => 10],
            ['label' => 'Useful page content (100+ words)', 'passed' => $wordCount >= 100, 'points' => 20],
            ['label' => 'Featured image present', 'passed' => filled($item->thumbnail), 'points' => 10],
        ];

        $score = collect($checks)->sum(fn ($check) => $check['passed'] ? $check['points'] : 0);

        return [
            'type' => $type,
            'title' => $item->title,
            'url' => $type === 'Course'
                ? route('admin.courses.edit', ['course' => $item->slug])
                : route('admin.posts.edit', ['post' => $item->slug]),
            'score' => $score,
            'checks' => $checks,
        ];
    }
}
