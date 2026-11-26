<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\LiveClass;
use App\Models\Enrollment;
use App\Models\OnlineBatchEnrollment;
use App\Models\User;
use App\Notifications\LiveClassChanged;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class LiveClassController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $classes = LiveClass::with(['course', 'batch'])
            ->when(! $user->isAdmin(), fn ($q) => $q
                ->whereHas('course', fn ($c) => $c->where('instructor_id', $user->id)))
            ->when($request->get('course'), fn ($q, $id) => $q->where('course_id', $id))
            ->orderByDesc('starts_at')
            ->paginate(15)->withQueryString();

        return view('admin.live-classes.index', [
            'classes' => $classes,
            'courses' => $this->courses(),
            'batches' => $this->batches(),
        ]);
    }

    public function calendar(Request $request)
    {
        $user = auth()->user();
        try {
            $month = Carbon::createFromFormat('Y-m', (string) $request->get('month', now()->format('Y-m')))->startOfMonth();
        } catch (\Throwable $exception) {
            $month = now()->startOfMonth();
        }

        $classes = LiveClass::with(['course', 'batch'])
            ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->when(! $user->isAdmin(), fn ($q) => $q->whereHas('course', fn ($course) => $course->where('instructor_id', $user->id)))
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (LiveClass $class) => $class->starts_at?->toDateString());

        return view('admin.live-classes.calendar', compact('month', 'classes'));
    }

    public function create()
    {
        return view('admin.live-classes.form', [
            'liveClass' => new LiveClass([
                'platform' => 'Zoom', 'starts_at' => now()->addDay()->startOfHour(),
                'duration_minutes' => 60, 'is_published' => true,
            ]),
            'courses' => $this->courses(),
            'batches' => $this->batches(),
            'platforms' => LiveClass::PLATFORMS,
        ]);
    }

    public function store(Request $request)
    {
        $liveClass = LiveClass::create($this->validated($request));
        if ($liveClass->is_published) {
            $this->notifyStudents($liveClass, 'scheduled');
        }

        return redirect()->route('admin.live-classes.index')->with('success', 'Live class scheduled.');
    }

    public function edit(LiveClass $liveClass)
    {
        $this->authorizeOwner($liveClass);

        return view('admin.live-classes.form', [
            'liveClass' => $liveClass,
            'courses' => $this->courses(),
            'batches' => $this->batches(),
            'platforms' => LiveClass::PLATFORMS,
        ]);
    }

    public function update(Request $request, LiveClass $liveClass)
    {
        $this->authorizeOwner($liveClass);
        $data = $this->validated($request);

        $previousStart = $liveClass->starts_at?->timestamp;
        $previousDuration = (int) $liveClass->duration_minutes;
        $previousCourseId = (int) $liveClass->course_id;
        $previousBatchId = $liveClass->batch_id ? (int) $liveClass->batch_id : null;
        $previousPublished = (bool) $liveClass->is_published;
        $previousDetails = [
            'title' => $liveClass->title,
            'platform' => $liveClass->platform,
            'meeting_url' => $liveClass->meeting_url,
            'description' => $liveClass->description,
            'recording_url' => $liveClass->recording_url,
        ];

        $updated = DB::transaction(function () use ($request, $liveClass, $data) {
            $lockedClass = LiveClass::query()->lockForUpdate()->findOrFail($liveClass->id);
            $this->authorizeOwner($lockedClass);
            $attendanceSession = AttendanceSession::query()
                ->where('live_class_id', $lockedClass->id)
                ->lockForUpdate()
                ->first();

            $scheduleOrAudienceChanged =
                Carbon::parse($data['starts_at'])->timestamp !== $lockedClass->starts_at?->timestamp
                || (int) $data['duration_minutes'] !== (int) $lockedClass->duration_minutes
                || (int) $data['course_id'] !== (int) $lockedClass->course_id
                || (($data['batch_id'] ?? null) ? (int) $data['batch_id'] : null)
                    !== ($lockedClass->batch_id ? (int) $lockedClass->batch_id : null);

            if ($attendanceSession
                && in_array($attendanceSession->status, ['submitted', 'approved'], true)
                && $scheduleOrAudienceChanged) {
                throw ValidationException::withMessages([
                    'starts_at' => 'This live class has submitted or approved attendance. Its course, batch and schedule can no longer be changed.',
                ]);
            }

            $lockedClass->update($data);

            if ($attendanceSession && in_array($attendanceSession->status, ['draft', 'returned'], true)) {
                $lockedClass->load(['course', 'batch']);
                $attendanceSession->update([
                    'course_id' => $lockedClass->course_id,
                    'batch_id' => $lockedClass->batch_id,
                    'branch_id' => $lockedClass->batch?->branch_id,
                    'instructor_id' => $lockedClass->course?->instructor_id ?: $attendanceSession->instructor_id,
                    'topic' => $lockedClass->title,
                    'starts_at' => $lockedClass->starts_at,
                    'ends_at' => $lockedClass->endsAt(),
                ]);
            }

            return $lockedClass;
        });

        $updated->refresh();
        $scheduleOrAudienceChanged =
            $previousStart !== $updated->starts_at?->timestamp
            || $previousDuration !== (int) $updated->duration_minutes
            || $previousCourseId !== (int) $updated->course_id
            || $previousBatchId !== ($updated->batch_id ? (int) $updated->batch_id : null);

        $detailsChanged = $previousDetails !== [
            'title' => $updated->title,
            'platform' => $updated->platform,
            'meeting_url' => $updated->meeting_url,
            'description' => $updated->description,
            'recording_url' => $updated->recording_url,
        ];

        $notificationEvent = match (true) {
            $previousPublished && ! $updated->is_published => 'unpublished',
            ! $previousPublished && $updated->is_published => 'scheduled',
            $updated->is_published && $scheduleOrAudienceChanged => 'rescheduled',
            $updated->is_published && $detailsChanged => 'updated',
            default => null,
        };

        if (! $updated->isCancelled() && $notificationEvent !== null) {
            $this->notifyStudents($updated, $notificationEvent);
        }

        return redirect()->route('admin.live-classes.index')->with('success', 'Live class updated.');
    }

    public function destroy(LiveClass $liveClass)
    {
        $this->authorizeOwner($liveClass);
        $liveClass->delete();

        return redirect()->route('admin.live-classes.index')->with('success', 'Live class deleted.');
    }

    public function cancel(Request $request, LiveClass $liveClass)
    {
        $this->authorizeOwner($liveClass);
        abort_if($liveClass->isCancelled(), 422, 'This live class is already cancelled.');

        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:500'],
        ]);

        $liveClass->update([
            'cancelled_at' => now(),
            'cancellation_reason' => $data['cancellation_reason'],
        ]);

        $this->notifyStudents($liveClass, 'cancelled');

        return redirect()->route('admin.live-classes.index')->with('success', 'Live class cancelled and enrolled students notified.');
    }

    public function reschedule(Request $request, LiveClass $liveClass)
    {
        $this->authorizeOwner($liveClass);
        abort_unless($liveClass->isCancelled(), 422, 'Only a cancelled class can be rescheduled.');
        abort_if(
            $liveClass->attendanceSession()->whereIn('status', ['submitted', 'approved'])->exists(),
            422,
            'This class attendance has already been submitted or approved and cannot be rescheduled.'
        );

        $data = $request->validate([
            'starts_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
        ]);

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = $startsAt->copy()->addMinutes((int) $data['duration_minutes']);
        $instructorId = $liveClass->course?->instructor_id;

        if ($instructorId) {
            $conflict = LiveClass::query()
                ->where('id', '!=', $liveClass->id)
                ->whereNull('cancelled_at')
                ->whereHas('course', fn ($query) => $query->where('instructor_id', $instructorId))
                ->get(['starts_at', 'duration_minutes'])
                ->contains(function (LiveClass $scheduled) use ($startsAt, $endsAt) {
                    $scheduledEnd = $scheduled->starts_at?->copy()->addMinutes((int) $scheduled->duration_minutes);

                    return $scheduled->starts_at && $scheduledEnd
                        && $scheduled->starts_at->lt($endsAt)
                        && $scheduledEnd->gt($startsAt);
                });

            if ($conflict) {
                throw ValidationException::withMessages([
                    'starts_at' => 'This teacher already has another live class during the selected time.',
                ]);
            }
        }

        $liveClass->update([
            'starts_at' => $startsAt,
            'duration_minutes' => $data['duration_minutes'],
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);

        $liveClass->attendanceSession()->update([
            'starts_at' => $startsAt,
            'ends_at' => $liveClass->endsAt(),
        ]);

        if ($liveClass->is_published) {
            $this->notifyStudents($liveClass, 'rescheduled');
        }

        return redirect()->route('admin.live-classes.index')->with('success', 'Live class rescheduled and students notified.');
    }

    private function notifyStudents(LiveClass $liveClass, string $event): void
    {
        // Notification recipients must satisfy the same branch and batch
        // eligibility rules as the student live-class schedule. A still-active
        // enrollment can be stale after a branch transfer or batch cancellation.
        $legacy = Enrollment::query()
            ->where('course_id', $liveClass->course_id)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($liveClass->batch_id, fn ($q) => $q->where('batch_id', $liveClass->batch_id))
            ->where(function ($eligibleBatch) {
                $eligibleBatch->whereNull('batch_id')
                    ->orWhereHas('batch', function ($batch) {
                        $batch->whereNotIn('status', ['upcoming', 'cancelled'])
                            ->where(function ($branch) {
                                $branch->whereNull('batches.branch_id')
                                    ->orWhereExists(function ($studentBranch) {
                                        $studentBranch->selectRaw('1')
                                            ->from('users')
                                            ->whereColumn('users.id', 'enrollments.user_id')
                                            ->whereColumn('users.branch_id', 'batches.branch_id');
                                    });
                            });
                    });
            })
            ->whereHas('user', fn ($q) => $q
                ->where('role', 'student')
                ->where('is_active', true)
                ->where(fn ($branch) => $branch->whereNull('enrollments.branch_id')
                    ->orWhereColumn('enrollments.branch_id', 'users.branch_id')))
            ->pluck('user_id');

        $online = OnlineBatchEnrollment::query()
            ->where('course_id', $liveClass->course_id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->when($liveClass->batch_id, fn ($q) => $q->where('batch_id', $liveClass->batch_id))
            ->whereHas('batch', function ($batch) {
                $batch->whereNotIn('status', ['upcoming', 'cancelled'])
                    ->where(function ($branch) {
                        $branch->whereNull('batches.branch_id')
                            ->orWhereExists(function ($studentBranch) {
                                $studentBranch->selectRaw('1')
                                    ->from('users')
                                    ->whereColumn('users.id', 'online_batch_enrollments.student_id')
                                    ->whereColumn('users.branch_id', 'batches.branch_id');
                            });
                    });
            })
            ->whereHas('student', fn ($q) => $q->where('role', 'student')->where('is_active', true))
            ->pluck('student_id');

        $studentIds = $legacy->merge($online)->unique()->values();
        if ($studentIds->isNotEmpty()) {
            $notification = new LiveClassChanged($liveClass, $event);

            Notification::send(
                User::query()->whereIn('id', $studentIds)->get(),
                $notification
            );

            // Guardians receive the same class lifecycle update for each linked child.
            $guardianIds = DB::table('guardian_students')
                ->whereIn('student_id', $studentIds)
                ->distinct()
                ->pluck('guardian_id');

            $guardians = User::query()
                ->whereIn('id', $guardianIds)
                ->where('role', 'guardian')
                ->where('is_active', true)
                ->get();

            Notification::send($guardians, $notification);
        }
    }

    private function authorizeOwner(LiveClass $liveClass): void
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $liveClass->course?->instructor_id === $user->id, 403);
    }

    private function courses()
    {
        $user = auth()->user();

        return Course::when(! $user->isAdmin(), fn ($q) => $q->where('instructor_id', $user->id))
            ->orderBy('title')->pluck('title', 'id');
    }

    private function batches()
    {
        return Batch::query()
            ->whereIn('course_id', $this->courses()->keys())
            ->where('status', '!=', 'cancelled')
            ->orderBy('name')
            ->get(['id', 'course_id', 'name', 'code']);
    }

    private function validated(Request $request): array
    {
        // Accept legacy lowercase/mixed-case platform values and persist the canonical label.
        $submittedPlatform = (string) $request->input('platform', '');
        $canonicalPlatform = collect(LiveClass::PLATFORMS)->first(
            fn (string $platform) => strcasecmp($platform, $submittedPlatform) === 0
        );
        if ($canonicalPlatform !== null) {
            $request->merge(['platform' => $canonicalPlatform]);
        }

        $courseId = (int) $request->input('course_id');
        $data = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'batch_id' => ['nullable', 'integer', Rule::exists('batches', 'id')->where(fn ($query) => $query->where('course_id', $courseId))],
            'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:2000',
            'platform' => ['required', 'string', Rule::in(LiveClass::PLATFORMS)],
            'meeting_url' => 'nullable|url|max:255',
            'meeting_password' => 'nullable|string|max:60',
            'recording_url' => 'nullable|url|max:255',
            'starts_at' => 'required|date',
            'duration_minutes' => 'required|integer|min:5|max:600',
        ]);

        $course = Course::findOrFail($data['course_id']);

        if (! auth()->user()->isAdmin()) {
            abort_unless((int) $course->instructor_id === (int) auth()->id(), 403);
        }

        // A teacher cannot be scheduled to teach two sessions at the same time,
        // even when the sessions belong to different courses. Exclude the record
        // being edited so an unchanged schedule remains valid.
        if ($course->instructor_id) {
            $startsAt = Carbon::parse($data['starts_at']);
            $endsAt = $startsAt->copy()->addMinutes((int) $data['duration_minutes']);

            $hasConflict = LiveClass::query()
                ->where('id', '!=', $request->route('liveClass')?->id ?? 0)
                ->whereNull('cancelled_at')
                ->whereHas('course', fn ($query) => $query->where('instructor_id', $course->instructor_id))
                ->get(['id', 'starts_at', 'duration_minutes'])
                ->contains(function (LiveClass $scheduled) use ($startsAt, $endsAt) {
                    $scheduledStart = $scheduled->starts_at;
                    $scheduledEnd = $scheduledStart?->copy()->addMinutes((int) $scheduled->duration_minutes);

                    return $scheduledStart && $scheduledEnd
                        && $scheduledStart->lt($endsAt)
                        && $scheduledEnd->gt($startsAt);
                });

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'starts_at' => 'This teacher already has a live class scheduled during the selected time.',
                ]);
            }
        }

        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }
}
