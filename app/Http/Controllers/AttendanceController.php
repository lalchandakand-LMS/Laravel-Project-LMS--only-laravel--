<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\LiveClass;
use App\Models\Batch;
use App\Models\CoachingBatch;
use App\Models\CoachingEnrollment;
use App\Models\Enrollment;
use App\Models\OnlineBatchEnrollment;
use App\Models\User;
use App\Notifications\AttendancePublishedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    private function canManage(Request $request, AttendanceSession $session): bool
    {
        $user = $request->user();

        return $user->isAdmin() || (int) $session->instructor_id === (int) $user->id;
    }

    private function activeStudentIds(AttendanceSession $session)
    {
        if ($session->coaching_batch_id) {
            return CoachingEnrollment::query()
                ->where('coaching_batch_id', $session->coaching_batch_id)
                ->where('status', 'active')
                ->whereHas('student', fn ($q) => $q->where('role', 'student')->where('is_active', true))
                ->pluck('student_id');
        }

        $legacy = Enrollment::query()
            ->where('status', 'active')
            ->where(fn ($q) => $session->batch_id
                ? $q->where('batch_id', $session->batch_id)
                : $q->where('course_id', $session->course_id)
                    ->where(fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->whereHas('user', fn ($q) => $q->where('role', 'student')->where('is_active', true))
            ->pluck('user_id');
        $online = OnlineBatchEnrollment::query()
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->where(fn ($q) => $session->batch_id
                ? $q->where('batch_id', $session->batch_id)
                : $q->where('course_id', $session->course_id))
            ->whereHas('student', fn ($q) => $q->where('role', 'student')->where('is_active', true))
            ->pluck('student_id');

        return $legacy->merge($online)->unique()->values();
    }

    public function teacherIndex(Request $request)
    {
        $user = $request->user();
        $sessions = AttendanceSession::withCount('records')
            ->with(['batch', 'coachingBatch', 'course', 'reviewer'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('instructor_id', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('starts_at')->paginate(20)->withQueryString();

        return view('attendance.teacher.index', compact('sessions'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $onlineBatches = Batch::with('course')
            ->whereIn('status', ['upcoming', 'ongoing'])
            ->whereNotNull('course_id')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('instructor_id', $user->id))
            ->orderBy('name')->get();

        $coachingBatches = CoachingBatch::with(['course', 'branch'])
            ->whereIn('status', ['upcoming', 'ongoing'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('instructor_id', $user->id))
            ->orderBy('name')->get();

        return view('attendance.teacher.create', compact('onlineBatches', 'coachingBatches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'batch_id' => ['nullable', 'required_without:coaching_batch_id', 'integer', 'exists:batches,id'],
            'coaching_batch_id' => ['nullable', 'required_without:batch_id', 'integer', 'exists:coaching_batches,id'],
            'topic' => ['nullable', 'string', 'max:180'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        abort_if(! empty($data['batch_id']) && ! empty($data['coaching_batch_id']), 422, 'একটি মাত্র ব্যাচ নির্বাচন করুন।');
        $user = $request->user();

        if (! empty($data['coaching_batch_id'])) {
            $batch = CoachingBatch::with('course')->findOrFail($data['coaching_batch_id']);
            abort_unless($user->isAdmin() || (int) $batch->instructor_id === (int) $user->id, 403);
            $sessionData = [
                'batch_id' => null,
                'coaching_batch_id' => $batch->id,
                'course_id' => $batch->course_id,
                'branch_id' => $batch->branch_id,
                'instructor_id' => $user->isAdmin() ? ($batch->instructor_id ?: $user->id) : $user->id,
                'mode' => 'coaching',
            ];
        } else {
            $batch = Batch::with('course')->findOrFail($data['batch_id']);
            abort_unless($user->isAdmin() || (int) $batch->instructor_id === (int) $user->id, 403);
            abort_if(! $batch->course_id, 422, 'এই অনলাইন ব্যাচে কোর্স নির্ধারণ করা নেই।');
            $sessionData = [
                'batch_id' => $batch->id,
                'coaching_batch_id' => null,
                'course_id' => $batch->course_id,
                'branch_id' => $batch->branch_id,
                'instructor_id' => $user->isAdmin() ? ($batch->instructor_id ?: $user->id) : $user->id,
                'mode' => 'online',
            ];
        }

        $session = AttendanceSession::create($sessionData + [
            'topic' => $data['topic'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'status' => 'draft',
        ]);

        return redirect()->route('teacher.attendance.show', $session)->with('success', 'Attendance session created. Mark each active student, then submit it to admin.');
    }

    public function show(Request $request, AttendanceSession $session)
    {
        $session->load(['batch', 'coachingBatch', 'course', 'instructor', 'reviewer', 'records.student']);
        abort_unless($this->canManage($request, $session), 403);

        $students = User::query()->whereIn('id', $this->activeStudentIds($session))
            ->orderBy('name')->get();
        $marked = $session->records->keyBy('student_id');

        return view('attendance.teacher.show', compact('session', 'students', 'marked'));
    }

    public function save(Request $request, AttendanceSession $session)
    {
        $data = $request->validate([
            'attendance' => ['required', 'array'],
            'attendance.*.status' => ['required', Rule::in(Attendance::STATUSES)],
            'attendance.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $submittedIds = array_map('strval', array_keys($data['attendance']));
        sort($submittedIds);

        DB::transaction(function () use ($session, $data, $request, $submittedIds) {
            // Save and submit share the same row lock so a stale save cannot
            // reopen or mutate a roster after it has been submitted.
            $lockedSession = AttendanceSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($this->canManage($request, $lockedSession), 403);
            abort_unless(in_array($lockedSession->status, ['draft', 'returned'], true), 422,
                'Only draft or returned attendance can be edited.');

            $expectedIds = $this->activeStudentIds($lockedSession)->map(fn ($id) => (string) $id)->all();
            sort($expectedIds);
            abort_unless($submittedIds === $expectedIds, 422,
                'Attendance must include every active student in this batch and no other student.');

            foreach ($data['attendance'] as $studentId => $record) {
                Attendance::updateOrCreate(
                    ['attendance_session_id' => $lockedSession->id, 'student_id' => $studentId],
                    ['status' => $record['status'], 'note' => $record['note'] ?? null, 'marked_by' => $request->user()->id]
                );
            }

            $lockedSession->update([
                'status' => 'draft',
                'submitted_at' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ]);
        });

        return back()->with('success', 'Attendance saved as draft.');
    }

    public function submit(Request $request, AttendanceSession $session)
    {
        DB::transaction(function () use ($session, $request) {
            $lockedSession = AttendanceSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($this->canManage($request, $lockedSession), 403);
            abort_unless(in_array($lockedSession->status, ['draft', 'returned'], true), 422);

            $expectedIds = $this->activeStudentIds($lockedSession)
                ->map(fn ($id) => (int) $id)
                ->sort()->values()->all();
            $recordedIds = $lockedSession->records()->pluck('student_id')
                ->map(fn ($id) => (int) $id)
                ->sort()->values()->all();

            abort_if(
                $expectedIds === [] || $recordedIds !== $expectedIds,
                422,
                'Attendance must match the current active student list before submitting.'
            );

            $lockedSession->update(['status' => 'submitted', 'submitted_at' => now(), 'review_note' => null]);
        });

        return back()->with('success', 'Attendance submitted to admin for review.');
    }

    public function adminIndex(Request $request)
    {
        $sessions = AttendanceSession::withCount('records')
            ->with(['batch', 'coachingBatch', 'course', 'instructor', 'reviewer'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->latest('starts_at')->paginate(25)->withQueryString();

        return view('attendance.admin.index', compact('sessions'));
    }

    public function adminShow(AttendanceSession $session)
    {
        $session->load(['batch', 'coachingBatch', 'course', 'instructor', 'reviewer', 'records.student']);

        return view('attendance.admin.show', compact('session'));
    }

    public function createFromLiveClass(Request $request, LiveClass $liveClass)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || (int) $liveClass->course?->instructor_id === (int) $user->id, 403);
        abort_if($liveClass->isCancelled(), 422, 'Cancelled live classes cannot create attendance sessions.');
        $batch = $liveClass->batch;
        abort_if($liveClass->batch_id && ! $batch, 422, 'The assigned batch no longer exists.');

        $session = AttendanceSession::firstOrCreate(
            ['live_class_id' => $liveClass->id],
            [
                'batch_id' => $batch?->id,
                'coaching_batch_id' => null,
                'course_id' => $liveClass->course_id,
                'branch_id' => $batch?->branch_id,
                'instructor_id' => $liveClass->course?->instructor_id ?: $user->id,
                'mode' => 'online',
                'topic' => $liveClass->title,
                'starts_at' => $liveClass->starts_at,
                'ends_at' => $liveClass->endsAt(),
                'status' => 'draft',
            ]
        );

        return redirect()->route('teacher.attendance.show', $session)
            ->with('success', 'Attendance session is linked to this live class.');
    }

    public function studentReport(Request $request)
    {
        $student = $request->user();
        $records = Attendance::with(['session.batch', 'session.coachingBatch', 'session.course', 'session.instructor'])
            ->where('student_id', $student->id)
            ->whereHas('session', fn ($q) => $q->where('status', 'approved'))
            ->latest()->paginate(25)->withQueryString();

        return view('attendance.reports.index', ['records' => $records, 'reportTitle' => 'My attendance']);
    }

    public function guardianReport(Request $request, User $child)
    {
        $guardian = $request->user();
        abort_unless($guardian->isAdmin() || $guardian->children()->whereKey($child->id)->exists(), 403);
        abort_unless($child->isStudent(), 404);

        $records = Attendance::with(['session.batch', 'session.coachingBatch', 'session.course', 'session.instructor'])
            ->where('student_id', $child->id)
            ->whereHas('session', fn ($q) => $q->where('status', 'approved'))
            ->latest()->paginate(25)->withQueryString();

        return view('attendance.reports.index', ['records' => $records, 'reportTitle' => $child->name.' — attendance report']);
    }

    public function review(Request $request, AttendanceSession $session)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'return'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless($session->status === 'submitted', 422, 'Only submitted attendance can be reviewed.');
        abort_if($data['action'] === 'return' && blank($data['review_note'] ?? null), 422, 'Add a note explaining what the teacher must correct.');

        $session->update([
            'status' => $data['action'] === 'approve' ? 'approved' : 'returned',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
        ]);

        if ($data['action'] === 'approve') {
            $session->load('records.student.guardians');
            foreach ($session->records as $record) {
                $notification = new AttendancePublishedNotification($session, $record);
                $record->student?->notify($notification);

                foreach ($record->student?->guardians ?? [] as $guardian) {
                    $guardian->notify($notification);
                }
            }
        }

        return back()->with('success', $data['action'] === 'approve' ? 'Attendance approved and published to student/guardian reports.' : 'Attendance returned to the teacher for correction.');
    }
}
