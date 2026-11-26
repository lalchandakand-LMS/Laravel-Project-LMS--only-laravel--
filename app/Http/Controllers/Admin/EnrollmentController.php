<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentHistory;
use App\Models\OnlineBatchEnrollment;
use App\Models\CoachingEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;

class EnrollmentController extends Controller
{
    private const STATUSES = ['active', 'suspended', 'completed', 'expired', 'cancelled'];

    private function filteredQuery(Request $request): Builder
    {
        $query = Enrollment::query()->with(['user.branch', 'course', 'batch'])->latest('enrolled_at')->latest('id');

        if ($request->filled('q')) {
            $term = trim($request->string('q')->toString());
            $query->where(function (Builder $search) use ($term) {
                $search->whereHas('user', fn ($user) => $user
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"))
                    ->orWhereHas('course', fn ($course) => $course->where('title', 'like', "%{$term}%"));
            });
        }

        foreach (['status', 'course_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('user', fn ($user) => $user->where('branch_id', $request->integer('branch_id')));
        }
        if ($request->filled('from')) {
            $query->whereDate('enrolled_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('enrolled_at', '<=', $request->date('to'));
        }

        return $query;
    }

    public function index(Request $request)
    {
        $enrollments = $this->filteredQuery($request)->paginate(20)->withQueryString();
        $enrollments->getCollection()->transform(function (Enrollment $enrollment) {
            $enrollment->progress_percent = $enrollment->course && $enrollment->user
                ? $enrollment->course->progressFor($enrollment->user)
                : 0;
            return $enrollment;
        });

        $onlineEnrollments = $this->onlineBatchQuery($request)->paginate(10, ['*'], 'online_page')->withQueryString();
        $coachingEnrollments = $this->coachingQuery($request)->paginate(10, ['*'], 'coaching_page')->withQueryString();

        return view('admin.enrollments.index', [
            'enrollments' => $enrollments,
            'onlineEnrollments' => $onlineEnrollments,
            'coachingEnrollments' => $coachingEnrollments,
            'onlinePending' => \\App\\Models\\OnlineBatchApplication::where('status', 'pending')->count(),
            'coachingPending' => \\App\\Models\\CoachingAdmission::where('status', 'pending')->count(),
            'courses' => Course::orderBy('title')->get(['id', 'title']),
            'students' => User::where('role', 'student')->orderBy('name')->get(['id', 'name', 'email', 'branch_id']),
            'batches' => Batch::with('course')->whereNotIn('status', ['cancelled'])->orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'statuses' => self::STATUSES,
            'filters' => $request->only(['q', 'status', 'course_id', 'branch_id', 'from', 'to']),
            'stats' => [
                'total' => Enrollment::count() + OnlineBatchEnrollment::count() + CoachingEnrollment::count(),
                'active' => Enrollment::where('status', 'active')->count()
                    + OnlineBatchEnrollment::where('status', 'active')->count()
                    + CoachingEnrollment::where('status', 'active')->count(),
                'completed' => Enrollment::where('status', 'completed')->count(),
                'suspended' => Enrollment::where('status', 'suspended')->count(),
                'course' => Enrollment::count(),
                'online' => OnlineBatchEnrollment::count(),
                'coaching' => CoachingEnrollment::count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request, true);
        $student = User::findOrFail($data['user_id']);
        abort_unless($student->role === 'student', 422, 'Only student accounts can be enrolled.');

        $course = Course::findOrFail($data['course_id']);
        $this->validateBatch($data, $student, $course);

        $enrollment = Enrollment::firstOrNew([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        if ($enrollment->exists && $enrollment->status === 'active'
            && (! $enrollment->expires_at || $enrollment->expires_at->isFuture())) {
            return back()->withErrors(['user_id' => 'This student already has an active enrollment in this course.'])->withInput();
        }

        $before = $enrollment->exists ? $enrollment->only(['batch_id', 'price_paid', 'status', 'enrolled_at', 'expires_at', 'admin_note']) : [];
        $enrollment->fill([
            'batch_id' => $data['batch_id'] ?? null,
            'price_paid' => $data['price_paid'],
            'status' => $data['status'],
            'enrolled_at' => $data['enrolled_at'],
            'expires_at' => ! empty($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null,
            'completed_at' => $data['status'] === 'completed' ? ($enrollment->completed_at ?? now()) : null,
            'admin_note' => $data['admin_note'] ?? null,
        ]);

        DB::transaction(function () use ($enrollment, $before, $data) {
            $enrollment->save();
            $this->recordHistory($enrollment, $before ? 'reactivated' : 'manually_created', $before, $enrollment->only(['batch_id', 'price_paid', 'status', 'enrolled_at', 'expires_at', 'admin_note']));
        });

        return redirect()->route('admin.enrollments.index')->with('success', 'Student enrollment saved successfully.');
    }

    public function show(Enrollment $enrollment)
    {
        $enrollment->load(['user.branch', 'course', 'batch', 'histories.actor']);
        $enrollment->progress_percent = $enrollment->course && $enrollment->user
            ? $enrollment->course->progressFor($enrollment->user) : 0;

        return view('admin.enrollments.show', compact('enrollment'));
    }

    public function edit(Enrollment $enrollment)
    {
        $enrollment->load(['user.branch', 'course', 'batch']);
        return view('admin.enrollments.edit', [
            'enrollment' => $enrollment,
            'batches' => Batch::where('course_id', $enrollment->course_id)->whereNotIn('status', ['cancelled'])->orderBy('name')->get(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(Request $request, Enrollment $enrollment)
    {
        $data = $this->validatedData($request, false);
        $student = $enrollment->user;
        $course = Course::findOrFail($enrollment->course_id);
        $this->validateBatch($data, $student, $course, $enrollment);

        $before = $enrollment->only(['batch_id', 'price_paid', 'status', 'enrolled_at', 'expires_at', 'admin_note', 'completed_at']);
        $enrollment->fill([
            'batch_id' => $data['batch_id'] ?? null,
            'price_paid' => $data['price_paid'],
            'status' => $data['status'],
            'enrolled_at' => $data['enrolled_at'],
            'expires_at' => ! empty($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null,
            'completed_at' => $data['status'] === 'completed' ? ($enrollment->completed_at ?? now()) : null,
            'admin_note' => $data['admin_note'] ?? null,
        ]);

        DB::transaction(function () use ($enrollment, $before) {
            $enrollment->save();
            $this->recordHistory($enrollment, 'updated', $before, $enrollment->only(array_keys($before)));
        });

        return redirect()->route('admin.enrollments.show', $enrollment)->with('success', 'Enrollment updated successfully.');
    }

    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'distinct', 'exists:enrollments,id'],
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        DB::transaction(function () use ($data) {
            $enrollments = Enrollment::whereIn('id', $data['enrollment_ids'])->get();
            foreach ($enrollments as $enrollment) {
                $before = ['status' => $enrollment->status, 'completed_at' => $enrollment->completed_at?->toISOString()];
                $enrollment->status = $data['status'];
                $enrollment->completed_at = $data['status'] === 'completed' ? ($enrollment->completed_at ?? now()) : null;
                $enrollment->save();
                $this->recordHistory($enrollment, 'bulk_status_changed', $before, [
                    'status' => $enrollment->status,
                    'completed_at' => $enrollment->completed_at?->toISOString(),
                ]);
            }
        });

        return back()->with('success', count($data['enrollment_ids']).' enrollment(s) updated.');
    }

    public function export(Request $request)
    {
        $rows = $this->filteredQuery($request)->get();
        $filename = 'enrollments-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Student', 'Email', 'Course', 'Batch', 'Branch', 'Progress %', 'Status', 'Paid', 'Enrolled At', 'Expires At']);
            foreach ($rows as $enrollment) {
                fputcsv($output, [
                    $enrollment->user?->name,
                    $enrollment->user?->email,
                    $enrollment->course?->title,
                    $enrollment->batch?->name,
                    $enrollment->user?->branch?->name,
                    $enrollment->course && $enrollment->user ? $enrollment->course->progressFor($enrollment->user) : 0,
                    $enrollment->status,
                    $enrollment->price_paid,
                    $enrollment->enrolled_at?->toDateString(),
                    $enrollment->expires_at?->toDateString(),
                ]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(Enrollment $enrollment)
    {
        $before = ['status' => $enrollment->status];
        $enrollment->status = 'cancelled';
        $enrollment->save();
        $this->recordHistory($enrollment, 'cancelled', $before, ['status' => 'cancelled']);

        return back()->with('success', 'Enrollment cancelled. Its payment and learning history have been preserved.');
    }

    private function onlineBatchQuery(Request $request): Builder
    {
        return OnlineBatchEnrollment::query()->with(['student.branch', 'course', 'batch.branch', 'application'])
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $term = trim($request->string('q')->toString());
                $query->where(fn (Builder $search) => $search
                    ->whereHas('student', fn ($student) => $student->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                    ->orWhereHas('course', fn ($course) => $course->where('title', 'like', "%{$term}%"))
                    ->orWhereHas('batch', fn ($batch) => $batch->where('name', 'like', "%{$term}%")));
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->when($request->filled('course_id'), fn (Builder $query) => $query->where('course_id', $request->integer('course_id')))
            ->when($request->filled('branch_id'), fn (Builder $query) => $query->whereHas('batch', fn ($batch) => $batch->where('branch_id', $request->integer('branch_id'))))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('admitted_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('admitted_at', '<=', $request->date('to')))
            ->latest('admitted_at')->latest('id');
    }

    private function coachingQuery(Request $request): Builder
    {
        return CoachingEnrollment::query()->with(['student.branch', 'course', 'batch.branch', 'admission'])
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $term = trim($request->string('q')->toString());
                $query->where(fn (Builder $search) => $search
                    ->whereHas('student', fn ($student) => $student->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                    ->orWhereHas('course', fn ($course) => $course->where('title', 'like', "%{$term}%"))
                    ->orWhereHas('batch', fn ($batch) => $batch->where('name', 'like', "%{$term}%")));
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->when($request->filled('course_id'), fn (Builder $query) => $query->where('course_id', $request->integer('course_id')))
            ->when($request->filled('branch_id'), fn (Builder $query) => $query->whereHas('batch', fn ($batch) => $batch->where('branch_id', $request->integer('branch_id'))))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('admitted_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('admitted_at', '<=', $request->date('to')))
            ->latest('admitted_at')->latest('id');
    }

    private function validatedData(Request $request, bool $creating): array
    {
        $rules = [
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'price_paid' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'enrolled_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:enrolled_at'],
            'admin_note' => ['nullable', 'string', 'max:5000'],
        ];

        if ($creating) {
            $rules['user_id'] = ['required', 'integer', 'exists:users,id'];
            $rules['course_id'] = ['required', 'integer', 'exists:courses,id'];
        } else {
            $rules['user_id'] = ['prohibited'];
            $rules['course_id'] = ['prohibited'];
        }

        return $request->validate($rules);
    }

    private function validateBatch(array $data, User $student, Course $course, ?Enrollment $current = null): void
    {
        if (empty($data['batch_id'])) {
            return;
        }

        $batch = Batch::findOrFail($data['batch_id']);
        if ((int) $batch->course_id !== (int) $course->id) {
            abort(422, 'Selected batch does not belong to this course.');
        }
        if ($batch->status === 'cancelled') {
            abort(422, 'A cancelled batch cannot receive enrollments.');
        }
        if ($batch->branch_id && $student->branch_id && (int) $batch->branch_id !== (int) $student->branch_id) {
            abort(422, 'Selected batch belongs to another branch.');
        }
        if ($batch->capacity) {
            $occupied = $batch->enrollments()->where('status', 'active')
                ->when($current, fn ($query) => $query->where('id', '!=', $current->id))
                ->count();
            if ($occupied >= $batch->capacity) {
                abort(422, 'This batch has reached its capacity.');
            }
        }
    }

    private function recordHistory(Enrollment $enrollment, string $action, array $before, array $after): void
    {
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $changes = [];
        foreach ($keys as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old instanceof \DateTimeInterface) $old = $old->toISOString();
            if ($new instanceof \DateTimeInterface) $new = $new->toISOString();
            if ($old != $new) $changes[$key] = ['from' => $old, 'to' => $new];
        }

        EnrollmentHistory::create([
            'enrollment_id' => $enrollment->id,
            'actor_id' => auth()->id(),
            'action' => $action,
            'changes' => $changes,
            'ip_address' => request()->ip(),
        ]);
    }
}
