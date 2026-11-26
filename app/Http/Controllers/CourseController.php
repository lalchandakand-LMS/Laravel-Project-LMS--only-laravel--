<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::published()->with(['instructor', 'category', 'categories']);

        if ($q = $request->get('q')) {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")
                ->orWhere('subtitle', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"));
        }

        if ($categorySlug = $request->get('category')) {
            $selectedCategory = Category::where('slug', $categorySlug)->where('is_active', true)->first();
            if ($selectedCategory) {
                $categoryIds = $selectedCategory->descendants()->pluck('id')->push($selectedCategory->id)->all();
                $query->where(fn ($q) => $q->whereIn('category_id', $categoryIds)
                    ->orWhereIn('id', DB::table('course_category')->select('course_id')->whereIn('category_id', $categoryIds)));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($level = $request->get('level')) {
            $query->where('level', $level);
        }

        match ($request->get('sort')) {
            'price_low' => $query->orderByRaw('COALESCE(discount_price, price) asc'),
            'price_high' => $query->orderByRaw('COALESCE(discount_price, price) desc'),
            'popular' => $query->withCount('enrollments')->orderByDesc('enrollments_count'),
            'rating' => $query->withAvg(['reviews as rating_avg' => fn ($r) => $r->where('is_approved', true)], 'rating')->orderByDesc('rating_avg'),
            default => $query->latest(),
        };

        return view('courses.index', [
            'courses' => $query->paginate(9)->withQueryString(),
            'categories' => Category::where('is_active', true)->withCount('publishedCourses')->orderBy('position')->orderBy('name')->get(),
            'filters' => $request->only('q', 'category', 'level', 'sort'),
        ]);
    }

    public function category(Category $category)
    {
        $categoryIds = $category->descendants()->pluck('id')->push($category->id)->all();

        return view('courses.index', [
            'courses' => Course::published()->where(fn ($query) => $query->whereIn('category_id', $categoryIds)->orWhereIn('id', DB::table('course_category')->select('course_id')->whereIn('category_id', $categoryIds)))
                ->with(['instructor', 'category', 'categories'])->latest()->paginate(9),
            'categories' => Category::where('is_active', true)->withCount('publishedCourses')->orderBy('position')->orderBy('name')->get(),
            'filters' => ['category' => $category->slug],
            'activeCategory' => $category,
        ]);
    }

    public function show(Request $request, Course $course)
    {
        abort_unless($course->is_published || optional($request->user())->isAdmin(), 404);

        $course->load(['sections.lessons', 'instructor', 'category', 'categories', 'liveClasses']);

        $user = $request->user();

        // Count one course detail view per browser session per course per day.
        // The session identifier is HMACed so raw session IDs are never stored.
        $visitorKey = hash_hmac('sha256', (string) $request->session()->getId(), (string) config('app.key'));
        DB::table('course_views')->insertOrIgnore([
            'course_id' => $course->id,
            'user_id' => $user?->id,
            'visitor_key' => $visitorKey,
            'viewed_on' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $enrolled = $user && $user->hasCourse($course);

        return view('courses.show', [
            'course' => $course,
            'enrolled' => $enrolled,
            'reviews' => $course->reviews()->with('user')->where('is_approved', true)->latest()->take(10)->get(),
            'related' => Course::published()->where('id', '!=', $course->id)->where(fn ($query) => $query->where('category_id', $course->category_id)->orWhereHas('categories', fn ($categories) => $categories->whereIn('categories.id', $course->categories->pluck('id'))))->take(3)->get(),
            'myReview' => $user ? $course->reviews()->where('user_id', $user->id)->first() : null,
            'exams' => $course->exams()->where('is_published', true)->get(),
        ]);
    }

    /** Free enrollment (price = 0). */
    public function enrollFree(Request $request, Course $course)
    {
        abort_unless($course->is_published, 404);
        abort_unless($course->final_price <= 0, 403);

        DB::transaction(function () use ($request, $course) {
            $lockedCourse = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            $existing = Enrollment::where('user_id', $request->user()->id)
                ->where('course_id', $lockedCourse->id)->first();

            if ($existing && $existing->status === 'active'
                && (! $existing->expires_at || $existing->expires_at->isFuture())) {
                return;
            }

            $activeSeats = Enrollment::where('course_id', $lockedCourse->id)
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count();

            abort_if($lockedCourse->seat_limit && $activeSeats >= $lockedCourse->seat_limit, 409,
                'This course has reached its enrollment capacity.');

            if ($existing) {
                $existing->update([
                    'price_paid' => 0,
                    'status' => 'active',
                    'enrolled_at' => now(),
                    'expires_at' => null,
                ]);
            } else {
                Enrollment::create([
                    'user_id' => $request->user()->id,
                    'course_id' => $lockedCourse->id,
                    'price_paid' => 0,
                    'status' => 'active',
                    'enrolled_at' => now(),
                ]);
            }
        });

        return redirect()->route('student.learn', $course->slug)
            ->with('success', 'You are enrolled. Happy learning!');
    }
}
