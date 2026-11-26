<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\LiveClass;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $period = in_array((int) $request->query('period', 6), [6, 12], true) ? (int) $request->query('period', 6) : 6;
        $now = now();
        $currentStart = $now->copy()->startOfMonth();
        $previousStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $currentStart->copy()->subSecond();

        $growth = function ($query) use ($currentStart, $previousStart, $previousEnd) {
            $current = (clone $query)->where('created_at', '>=', $currentStart)->count();
            $previous = (clone $query)->whereBetween('created_at', [$previousStart, $previousEnd])->count();
            $percent = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : ($current > 0 ? 100 : 0);

            return ['current' => $current, 'previous' => $previous, 'percent' => $percent];
        };

        $studentQuery = User::where('role', 'student');
        $teacherQuery = User::where('role', 'instructor');
        $courseQuery = Course::query();
        $batchQuery = Batch::query();

        $months = collect(range($period - 1, 0))->map(fn ($offset) => $now->copy()->subMonths($offset)->startOfMonth());
        $analytics = [
            'period' => $period,
            'labels' => $months->map(fn ($month) => $month->format('M'))->all(),
            'students' => $months->map(fn ($month) => (clone $studentQuery)->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count())->all(),
            'enrollments' => $months->map(fn ($month) => Enrollment::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count())->all(),
            'course_views' => $months->map(fn ($month) => DB::table('course_views')->whereBetween('viewed_on', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->count())->all(),
        ];

        $progressRows = Enrollment::with(['user', 'course'])
            ->where('status', 'active')->latest()->take(300)->get()
            ->filter(fn ($enrollment) => $enrollment->user && $enrollment->course)
            ->map(fn ($enrollment) => $enrollment->progressPercent());
        $progress = [
            'completed' => $progressRows->filter(fn ($value) => $value >= 100)->count(),
            'in_progress' => $progressRows->filter(fn ($value) => $value > 0 && $value < 100)->count(),
            'not_started' => $progressRows->filter(fn ($value) => $value <= 0)->count(),
            'overall' => $progressRows->isEmpty() ? 0 : (int) round($progressRows->avg()),
            'sample_size' => $progressRows->count(),
        ];

        $liveClass = LiveClass::with(['course', 'batch'])
            ->where('is_published', true)->whereNull('cancelled_at')
            ->whereBetween('starts_at', [$now->copy()->subHours(8), $now])
            ->latest('starts_at')->get()->first(fn ($class) => $class->isLive());

        $upcomingClasses = LiveClass::with(['course', 'batch'])
            ->where('is_published', true)->whereNull('cancelled_at')
            ->where('starts_at', '>=', $now)->orderBy('starts_at')->take(4)->get();

        $topStudents = ExamAttempt::query()
            ->selectRaw('user_id, SUM(score) as total_score, SUM(max_score) as total_max')
            ->where('status', 'submitted')->groupBy('user_id')
            ->orderByRaw('CASE WHEN SUM(max_score) > 0 THEN SUM(score) / SUM(max_score) ELSE 0 END DESC')
            ->with('user')->take(3)->get()
            ->filter(fn ($attempt) => $attempt->user)
            ->map(function ($attempt) {
                $attempt->score_percent = (float) $attempt->total_max > 0
                    ? round(((float) $attempt->total_score / (float) $attempt->total_max) * 100, 1) : 0;
                return $attempt;
            });

        $recentActivities = DB::table('activity_logs')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->select('activity_logs.event', 'activity_logs.created_at', 'users.name as actor_name')
            ->orderByDesc('activity_logs.created_at')->limit(6)->get();

        return view('admin.dashboard', [
            'stats' => [
                'students' => (clone $studentQuery)->count(),
                'teachers' => (clone $teacherQuery)->count(),
                'courses' => (clone $courseQuery)->count(),
                'batches' => (clone $batchQuery)->count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'revenue' => (float) Order::where('status', 'paid')->sum('total'),
                'unread_messages' => Message::where('recipient_id', auth()->id())->whereNull('read_at')->count(),
            ],
            'growth' => [
                'students' => $growth($studentQuery),
                'teachers' => $growth($teacherQuery),
                'courses' => $growth($courseQuery),
                'batches' => $growth($batchQuery),
            ],
            'analytics' => $analytics,
            'progress' => $progress,
            'liveClass' => $liveClass,
            'liveAttendanceCount' => $liveClass?->attendanceSession?->records()->count() ?? 0,
            'upcomingClasses' => $upcomingClasses,
            'topStudents' => $topStudents,
            'recentActivities' => $recentActivities,
            'latestStudents' => User::where('role', 'student')->latest()->take(1)->get(),
        ]);
    }

    public function search(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $results = collect();

        User::whereIn('role', ['student', 'instructor'])->where(function ($query) use ($like) {
            $query->where('name', 'like', $like)->orWhere('email', 'like', $like);
        })->limit(5)->get(['id', 'name', 'role'])->each(fn ($user) => $results->push([
            'label' => $user->name, 'type' => $user->role === 'instructor' ? 'Teacher' : 'Student',
            'url' => route('admin.users.show', $user),
        ]));

        Course::where('title', 'like', $like)->limit(5)->get(['id', 'title', 'slug'])->each(fn ($course) => $results->push([
            'label' => $course->title, 'type' => 'Course', 'url' => route('admin.courses.edit', $course),
        ]));

        Batch::where('name', 'like', $like)->limit(4)->get(['id', 'name'])->each(fn ($batch) => $results->push([
            'label' => $batch->name, 'type' => 'Batch', 'url' => route('admin.batches.show', $batch),
        ]));

        return response()->json(['results' => $results->take(10)->values()]);
    }
}
