<?php

namespace App\Http\Controllers;

use App\Models\Setting;
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
        $request->validate([
            'name' => 'required|max:100',
            'email' => 'required|email',
            'message' => 'required|max:2000',
        ]);

        // In production: Mail::to(Setting::get('contact_email'))->send(new ContactMail(...));
        logger()->info('Contact message', $request->only('name', 'email', 'message'));

        return back()->with('success', 'Your message has been sent. We will reply soon!');
    }
}
