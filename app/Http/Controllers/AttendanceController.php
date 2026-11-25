<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    private function canManage(Request $request, AttendanceSession $session): bool
    {
        $user = $request->user();
        return $user->isAdmin() || (
            (int) $session->instructor_id === (int) $user->id
            && (int) $session->batch?->instructor_id === (int) $user->id
        );
    }

    public function teacherIndex(Request $request)
    {
        $user = $request->user();
        $sessions = AttendanceSession::withCount('records')->with(['batch', 'course', 'reviewer'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('instructor_id', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('starts_at')->paginate(20)->withQueryString();

        return view('attendance.teacher.index', compact('sessions'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $batches = Batch::with('course')
            ->where('status', '!=', 'cancelled')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('instructor_id', $user->id))
            ->orderBy('name')->get();

        return view('attendance.teacher.create', compact('batches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            'mode' => ['required', Rule::in(AttendanceSession::MODES)],
            'topic' => ['nullable', 'string', 'max:180'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $user = $request->user();
        $batch = Batch::with('course')->findOrFail($data['batch_id']);
        abort_unless($user->isAdmin() || (int) $batch->instructor_id === (int) $user->id, 403);
        abort_if(! $batch->course_id, 422, 'This batch must have a course before attendance can be taken.');

        $session = AttendanceSession::create([
            'batch_id' => $batch->id,
            'course_id' => $batch->course_id,
            'branch_id' => $batch->branch_id,
            'instructor_id' => $user->isAdmin() ? ($batch->instructor_id ?: $user->id) : $user->id,
            'mode' => $data['mode'],
            'topic' => $data['topic'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'status' => 'draft',
        ]);

        return redirect()->route('teacher.attendance.show', $session)->with('success', 'Attendance session created. Mark each enrolled student, then submit it to admin.');
    }

    public function show(Request $request, AttendanceSession $session)
    {
        $session->load(['batch', 'course', 'instructor', 'reviewer', 'records.student']);
        abort_unless($this->canManage($request, $session), 403);

        $students = Enrollment::with('user')
            ->where('batch_id', $session->batch_id)
            ->where('status', 'active')
            ->whereHas('user', fn ($q) => $q->where('role', 'student')->where('is_active', true))
            ->get()->pluck('user')->sortBy('name')->values();

        $marked = $session->records->keyBy('student_id');
        return view('attendance.teacher.show', compact('session', 'students', 'marked'));
    }

    public function save(Request $request, AttendanceSession $session)
    {
        abort_unless($this->canManage($request, $session), 403);
        abort_unless(in_array($session->status, ['draft', 'returned'], true), 422, 'Only draft or returned attendance can be edited.');

        $studentIds = Enrollment::where('batch_id', $session->batch_id)
            ->where('status', 'active')->whereHas('user', fn ($q) => $q->where('role', 'student')->where('is_active', true))
            ->pluck('user_id')->map(fn ($id) => (string) $id)->all();

        $data = $request->validate([
            'attendance' => ['required', 'array'],
            'attendance.*.status' => ['required', Rule::in(Attendance::STATUSES)],
            'attendance.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $submittedIds = array_map('strval', array_keys($data['attendance']));
        sort($submittedIds);
        $expectedIds = $studentIds;
        sort($expectedIds);
        abort_unless($submittedIds === $expectedIds, 422, 'Attendance must include every active student in this batch and no other student.');

        DB::transaction(function () use ($session, $data, $request) {
            foreach ($data['attendance'] as $studentId => $record) {
                Attendance::updateOrCreate(
                    ['attendance_session_id' => $session->id, 'student_id' => $studentId],
                    ['status' => $record['status'], 'note' => $record['note'] ?? null, 'marked_by' => $request->user()->id]
                );
            }
            $session->update(['status' => 'draft', 'submitted_at' => null, 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null]);
        });

        return back()->with('success', 'Attendance saved as draft.');
    }

    public function submit(Request $request, AttendanceSession $session)
    {
        abort_unless($this->canManage($request, $session), 403);
        abort_unless(in_array($session->status, ['draft', 'returned'], true), 422);
        $expected = Enrollment::where('batch_id', $session->batch_id)->where('status', 'active')
            ->whereHas('user', fn ($q) => $q->where('role', 'student')->where('is_active', true))->count();
        $actual = $session->records()->count();
        abort_if($expected === 0 || $actual !== $expected, 422, 'Mark attendance for every active student before submitting.');

        $session->update(['status' => 'submitted', 'submitted_at' => now(), 'review_note' => null]);
        return back()->with('success', 'Attendance submitted to admin for review.');
    }

    public function adminIndex(Request $request)
    {
        $sessions = AttendanceSession::withCount('records')->with(['batch', 'course', 'instructor', 'reviewer'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->latest('starts_at')->paginate(25)->withQueryString();

        return view('attendance.admin.index', compact('sessions'));
    }

    public function adminShow(AttendanceSession $session)
    {
        $session->load(['batch', 'course', 'instructor', 'reviewer', 'records.student']);
        return view('attendance.admin.show', compact('session'));
    }

    public function studentReport(Request $request)
    {
        $student = $request->user();
        $batchIds = Enrollment::where('user_id', $student->id)->where('status', 'active')->whereNotNull('batch_id')->pluck('batch_id');
        $records = Attendance::with(['session.batch', 'session.course', 'session.instructor'])
            ->where('student_id', $student->id)
            ->whereHas('session', fn ($q) => $q->where('status', 'approved')->whereIn('batch_id', $batchIds))
            ->latest()->paginate(25)->withQueryString();

        return view('attendance.reports.index', ['records' => $records, 'reportTitle' => 'My attendance']);
    }

    public function guardianReport(Request $request, \App\Models\User $child)
    {
        $guardian = $request->user();
        abort_unless($guardian->isAdmin() || $guardian->children()->whereKey($child->id)->exists(), 403);
        abort_unless($child->isStudent(), 404);
        $batchIds = Enrollment::where('user_id', $child->id)->where('status', 'active')->whereNotNull('batch_id')->pluck('batch_id');
        $records = Attendance::with(['session.batch', 'session.course', 'session.instructor'])
            ->where('student_id', $child->id)
            ->whereHas('session', fn ($q) => $q->where('status', 'approved')->whereIn('batch_id', $batchIds))
            ->latest()->paginate(25)->withQueryString();

        return view('attendance.reports.index', ['records' => $records, 'reportTitle' => $child->name.\' — attendance report\']);
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

        return back()->with('success', $data['action'] === 'approve' ? 'Attendance approved and published to student/guardian reports.' : 'Attendance returned to the teacher for correction.');
    }
}
