<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\Review;
use App\Models\Lesson;
use App\Models\LiveClass;
use App\Models\Exam;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\User;

class HomeController extends Controller
{
    public function index()
    {
        return view('home.index', [
            'sliders' => Slider::active()->get(),
            'categories' => Category::where('is_active', true)->whereNull('parent_id')->withCount('publishedCourses')->orderBy('position')->get(),
            'featured' => Course::published()->featured()->with(['instructor', 'category'])->withCount('lessons', 'enrollments')->latest()->take(6)->get(),
            'latest' => Course::published()->with(['instructor', 'category'])->withCount('lessons', 'enrollments')->latest()->take(8)->get(),
            'ebooks' => Ebook::where('is_published', true)->latest()->take(4)->get(),
            'reviews' => Review::with(['user', 'course'])->where('is_approved', true)->latest()->take(6)->get(),
            'teachers' => User::where('role', 'instructor')->where('is_active', true)->withCount(['courses' => fn ($query) => $query->where('is_published', true)])->where(function ($query) { $query->whereNotNull('bio')->orWhereNotNull('designation'); })->orderByDesc('courses_count')->take(6)->get(),
            'upcomingLiveClasses' => LiveClass::published()->where('starts_at', '>=', now())->whereHas('course', fn ($query) => $query->where('is_published', true))->with('course')->orderBy('starts_at')->take(6)->get(),
            'upcomingExams' => Exam::where('is_published', true)->where(function ($query) { $query->whereNull('starts_at')->orWhere('starts_at', '>=', now()); })->whereHas('course', fn ($query) => $query->where('is_published', true))->with('course')->orderByRaw('starts_at is null')->orderBy('starts_at')->take(6)->get(),
            'homepageSettings' => Setting::all_(),
            'stats' => [
                'students' => User::where('role', 'student')->where('is_active', true)->count(),
                'courses' => Course::published()->count(),
                'instructors' => User::where('role', 'instructor')->where('is_active', true)->count(),
                'lessons' => Lesson::whereHas('course', fn ($query) => $query->where('is_published', true))->count(),
            ],
        ]);
    }
}
