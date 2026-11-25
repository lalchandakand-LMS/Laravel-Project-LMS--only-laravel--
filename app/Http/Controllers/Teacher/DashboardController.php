<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\LiveClass;
use App\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Course ids owned by the signed-in teacher (admins fall back to everything). */
    private function scopeIds(User $user): array
    {
        return Course::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->where('instructor_id', $user->id))
            ->pluck('id')->all();
    }

    public function index()
    {
        $user = auth()->user();
        $ids = $this->scopeIds($user);

        $enrollments = Enrollment::whereIn('course_id', $ids);

        $stats = [
            'courses' => count($ids),
            'students' => (clone $enrollments)->distinct('user_id')->count('user_id'),
            'active' => (clone $enrollments)->where('status', 'active')->count(),
            'revenue' => $this->revenueRows($ids)->sum('amount'),
            'rating' => $ids ? round((float) Review::whereIn('course_id', $ids)->where('is_approved', true)->avg('rating'), 1) : 0.0,
            'exams' => Exam::whereIn('course_id', $ids)->count(),
        ];

        $recent = (clone $enrollments)->with(['user', 'course'])
            ->latest()->take(8)->get();

        $courses = Course::whereIn('id', $ids)
            ->withCount(['enrollments', 'lessons'])
            ->orderByDesc('enrollments_count')->take(5)->get();

        $upcoming = LiveClass::whereIn('course_id', $ids)
            ->where('starts_at', '>=', now()->subMinutes(30))
            ->orderBy('starts_at')->take(5)->get();

        // 7-day enrolment trend for the little chart
        $trend = collect(range(6, 0))->map(function ($d) use ($ids) {
            $day = now()->subDays($d);
            return [
                'label' => $day->format('D'),
                'count' => Enrollment::whereIn('course_id', $ids)->whereDate('created_at', $day->toDateString())->count(),
            ];
        });

        return view('teacher.dashboard', compact('stats', 'recent', 'courses', 'upcoming', 'trend'));
    }

    public function courses()
    {
        $ids = $this->scopeIds(auth()->user());

        return view('teacher.courses', [
            'courses' => Course::whereIn('id', $ids)->with('category')
                ->withCount(['enrollments', 'lessons', 'exams', 'reviews'])
                ->latest()->paginate(12),
        ]);
    }

    public function students(Request $request)
    {
        $ids = $this->scopeIds(auth()->user());

        $students = User::query()
            ->where('role', 'student')
            ->whereHas('enrollments', fn ($q) => $q->whereIn('course_id', $ids))
            ->when($request->get('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->withCount(['enrollments' => fn ($q) => $q->whereIn('course_id', $ids)])
            ->orderBy('name')->paginate(20)->withQueryString();

        // attach best exam score per student for quick insight
        $scores = ExamAttempt::whereIn('exam_id', Exam::whereIn('course_id', $ids)->pluck('id'))
            ->whereIn('user_id', $students->pluck('id'))
            ->select('user_id', DB::raw('AVG(score) as avg_score'))
            ->groupBy('user_id')->pluck('avg_score', 'user_id');

        $progress = $students->getCollection()->mapWithKeys(function (User $student) use ($ids) {
            $enrollments = $student->enrollments()->whereIn('course_id', $ids)->with('course')->get();
            $values = $enrollments->filter(fn ($enrollment) => $enrollment->course)
                ->map(fn ($enrollment) => $enrollment->course->progressFor($student));

            return [$student->id => $values->isEmpty() ? 0 : round($values->avg(), 1)];
        });

        $lastActivity = $students->getCollection()->mapWithKeys(function (User $student) {
            return [$student->id => $student->lessonProgress()->latest('updated_at')->value('updated_at')];
        });

        return view('teacher.students', compact('students', 'scores', 'progress', 'lastActivity'));
    }

    public function student(User $user)
    {
        abort_unless($user->isStudent(), 404);
        $ids = $this->scopeIds(auth()->user());

        $enrollments = $user->enrollments()->whereIn('course_id', $ids)->with(['course', 'batch'])->get();
        abort_if($enrollments->isEmpty(), 403, 'This student is not in your courses.');

        $enrollments->each(function ($enrollment) use ($user) {
            if (! $enrollment->course) {
                $enrollment->progress_percent = 0;
                $enrollment->completed_lessons = 0;
                $enrollment->total_lessons = 0;
                return;
            }

            $lessonIds = $enrollment->course->lessons()->where('is_published', true)->pluck('id');
            $enrollment->total_lessons = $lessonIds->count();
            $enrollment->completed_lessons = $user->lessonProgress()
                ->whereIn('lesson_id', $lessonIds)->whereNotNull('completed_at')->count();
            $enrollment->progress_percent = $enrollment->total_lessons
                ? round($enrollment->completed_lessons / $enrollment->total_lessons * 100, 1)
                : 0;
        });

        return view('teacher.student-show', [
            'student' => $user,
            'enrollments' => $enrollments,
            'attempts' => $user->examAttempts()->whereHas('exam', fn ($q) => $q->whereIn('course_id', $ids))
                ->with('exam')->latest()->take(10)->get(),
            'certificates' => $user->certificates()->whereIn('course_id', $ids)->with('course')->get(),
        ]);
    }

    public function earnings()
    {
        $ids = $this->scopeIds(auth()->user());
        $rows = $this->revenueRows($ids);

        $months = collect(range(5, 0))->map(function ($m) use ($rows) {
            $start = now()->subMonths($m)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            return [
                'label' => $start->format('M'),
                'amount' => round($rows->filter(fn ($row) => $row->created_at >= $start && $row->created_at <= $end)->sum('amount'), 2),
            ];
        });

        $total = round($rows->sum('amount'), 2);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 25;
        $paginatedRows = new LengthAwarePaginator(
            $rows->sortByDesc('created_at')->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('teacher.earnings', [
            'months' => $months,
            'total' => $total,
            'rows' => $paginatedRows,
        ]);
    }

    /**
     * Allocate verified payments and refunds to owned courses by each course item's
     * share of the order value. This avoids counting an entire multi-course order
     * once for every instructor.
     */
    private function revenueRows(array $courseIds)
    {
        if ($courseIds === []) {
            return collect();
        }

        $courses = Course::whereIn('id', $courseIds)->get()->keyBy('id');
        $orders = Order::with([
            'user',
            'items',
            'payments' => fn ($query) => $query->where('status', 'verified'),
            'adjustments' => fn ($query) => $query->where('type', 'refund'),
        ])->whereHas('items', fn ($query) => $query->where('item_type', 'course')->whereIn('item_id', $courseIds))
            ->get();

        $rows = collect();
        foreach ($orders as $order) {
            $allItemsValue = (float) $order->items->sum(fn ($item) => (float) $item->price * max(1, (int) $item->qty));
            if ($allItemsValue <= 0) {
                continue;
            }

            $ownedItems = $order->items->filter(fn ($item) =>
                $item->item_type === 'course' && in_array((int) $item->item_id, $courseIds, true)
            );

            foreach ($ownedItems as $item) {
                $course = $courses->get($item->item_id);
                if (! $course) {
                    continue;
                }

                $share = ((float) $item->price * max(1, (int) $item->qty)) / $allItemsValue;
                foreach ($order->payments as $payment) {
                    $rows->push((object) [
                        'user' => $order->user,
                        'course' => $course,
                        'type' => 'payment',
                        'gateway' => $payment->gateway,
                        'reference' => $payment->transaction_id,
                        'amount' => round((float) $payment->amount * $share, 2),
                        'status' => 'verified',
                        'created_at' => $payment->created_at,
                    ]);
                }

                foreach ($order->adjustments as $adjustment) {
                    $rows->push((object) [
                        'user' => $order->user,
                        'course' => $course,
                        'type' => 'refund',
                        'gateway' => 'refund',
                        'reference' => $adjustment->reference,
                        'amount' => -round((float) $adjustment->amount * $share, 2),
                        'status' => 'recorded',
                        'created_at' => $adjustment->created_at,
                    ]);
                }
            }
        }

        return $rows;
    }

    public function liveClasses()
    {
        $ids = $this->scopeIds(auth()->user());

        return view('teacher.live-classes', [
            'classes' => LiveClass::whereIn('course_id', $ids)->with('course')
                ->orderByDesc('starts_at')->paginate(15),
        ]);
    }
}
