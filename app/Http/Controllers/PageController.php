<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\MarketingLead;
use App\Models\Category;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\Post;
use Illuminate\Http\Request;

class PageController extends Controller
{

    public function sitemap()
    {
        $urls = [
            ['loc' => route('home')],
            ['loc' => route('about')],
            ['loc' => route('contact')],
            ['loc' => route('courses.index')],
            ['loc' => route('blog.index')],
            ['loc' => route('ebooks.index')],
            ['loc' => route('coaching-admission.create')],
        ];

        foreach (Category::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereHas('publishedCourses')
                    ->orWhereHas('courses', fn ($courses) => $courses->where('is_published', true));
            })
            ->get(['slug', 'updated_at']) as $category) {
            $urls[] = ['loc' => route('categories.show', $category->slug), 'lastmod' => $category->updated_at];
        }

        foreach (Course::query()->where('is_published', true)->get(['slug', 'updated_at']) as $course) {
            $urls[] = ['loc' => route('courses.show', $course->slug), 'lastmod' => $course->updated_at];
        }

        foreach (Post::published()->get(['slug', 'updated_at']) as $post) {
            $urls[] = ['loc' => route('blog.show', $post->slug), 'lastmod' => $post->updated_at];
        }

        foreach (Ebook::query()->where('is_published', true)->get(['slug', 'updated_at']) as $ebook) {
            $urls[] = ['loc' => route('ebooks.show', $ebook->slug), 'lastmod' => $ebook->updated_at];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $entry) {
            $location = htmlspecialchars($entry['loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $xml .= '<url><loc>'.$location.'</loc>';

            if (! empty($entry['lastmod'])) {
                $xml .= '<lastmod>'.htmlspecialchars($entry['lastmod']->toAtomString(), ENT_XML1 | ENT_COMPAT, 'UTF-8').'</lastmod>';
            }

            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function about()
    {
        return view('home.about');
    }

    public function contact()
    {
        return view('home.contact');
    }

    public function sendContact(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:2000'],
            'website' => ['nullable', 'string', 'max:200'],
        ]);

        // A filled honeypot is treated as a successful submission but is not stored.
        if (filled($validated['website'] ?? null)) {
            return back()->with('success', 'Your message has been sent. We will reply soon!');
        }

        $attribution = $request->session()->get('marketing_attribution', []);
        $attribution = is_array($attribution) ? $attribution : [];

        MarketingLead::create([
            'user_id' => $request->user()?->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'],
            'source' => mb_substr((string) ($attribution['utm_source'] ?? ($attribution['referrer_host'] ?? 'website')), 0, 80),
            'utm_source' => $attribution['utm_source'] ?? null,
            'utm_medium' => $attribution['utm_medium'] ?? null,
            'utm_campaign' => $attribution['utm_campaign'] ?? null,
            'utm_term' => $attribution['utm_term'] ?? null,
            'utm_content' => $attribution['utm_content'] ?? null,
            'landing_page' => mb_substr((string) ($attribution['landing_page'] ?? '/contact'), 0, 500),
            'referrer_host' => $attribution['referrer_host'] ?? null,
            'status' => 'new',
        ]);

        return back()->with('success', 'Your message has been sent. We will reply soon!');
    }
}
