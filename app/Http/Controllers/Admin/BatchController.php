<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        $batches = Batch::with(['course', 'level', 'branch', 'instructor'])
            ->withCount(['enrollments as active_students_count' => fn ($query) => $query->where('status', 'active')])
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('code', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest('starts_at')->paginate(20)->withQueryString();

        return view('admin.batches.index', compact('batches'));
    }

    public function create() { return view('admin.batches.form', $this->formData(new Batch(['status' => 'upcoming']))); }

    public function store(Request $request)
    {
        Batch::create($this->validated($request));
        return redirect()->route('admin.batches.index')->with('success', 'Batch created.');
    }

    public function edit(Batch $batch) { return view('admin.batches.form', $this->formData($batch)); }

    public function update(Request $request, Batch $batch)
    {
        $batch->update($this->validated($request, $batch));
        return redirect()->route('admin.batches.index')->with('success', 'Batch updated.');
    }

    public function destroy(Batch $batch)
    {
        $batch->delete();
        return redirect()->route('admin.batches.index')->with('success', 'Batch moved to the Recycle Bin. Enrollments are preserved.');
    }


    public function show(Request $request, Batch $batch)
    {
        $batch->load(['course', 'level', 'branch', 'instructor']);
        $enrollments = $batch->enrollments()->with('user')
            ->whereHas('user', fn ($query) => $query->where('role', 'student'))
            ->latest()->paginate(20);
        $students = User::where('role', 'student')->where('is_active', true)
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('email', 'like', '%'.$request->string('q').'%')))
            ->orderBy('name')->limit(100)->get();
        return view('admin.batches.show', compact('batch', 'enrollments', 'students'));
    }

    public function assignStudent(Request $request, Batch $batch)
    {
        abort_if(! $batch->course_id, 422, 'Assign a course to this batch before enrolling students.');
        $data = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'student')->where('is_active', true))],
        ]);
        $enrollment = Enrollment::where('user_id', $data['student_id'])->where('course_id', $batch->course_id)->first();
        if ((int) ($enrollment?->batch_id) !== (int) $batch->id && $batch->capacity && $batch->activeStudentCount() >= $batch->capacity) {
            return back()->with('error', 'This batch has reached its student capacity.');
        }
        if ($enrollment && $enrollment->batch_id && (int) $enrollment->batch_id !== (int) $batch->id) {
            return back()->with('error', 'This student is already assigned to another batch for the same course.');
        }
        if ($enrollment) {
            $enrollment->update(['batch_id' => $batch->id, 'status' => 'active']);
        } else {
            Enrollment::create([
                'user_id' => $data['student_id'], 'course_id' => $batch->course_id,
                'batch_id' => $batch->id, 'status' => 'active', 'price_paid' => 0, 'enrolled_at' => now(),
            ]);
        }
        return back()->with('success', 'Student assigned to the batch.');
    }

    public function removeStudent(Batch $batch, Enrollment $enrollment)
    {
        abort_unless((int) $enrollment->batch_id === (int) $batch->id, 404);
        $enrollment->update(['batch_id' => null]);
        return back()->with('success', 'Student removed from this batch. Course enrollment and learning history remain active.');
    }

    private function formData(Batch $batch): array
    {
        return [
            'batch' => $batch,
            'courses' => Course::orderBy('title')->get(['id', 'title']),
            'levels' => Level::where('is_active', true)->orderBy('position')->orderBy('name')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
            'instructors' => User::where('role', 'instructor')->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?Batch $batch = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('batches', 'code')->ignore($batch?->id)],
            'course_id' => 'nullable|exists:courses,id',
            'level_id' => 'nullable|exists:levels,id',
            'branch_id' => 'nullable|exists:branches,id',
            'instructor_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string|max:3000',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'schedule_days' => 'nullable|string|max:120',
            'schedule_time' => 'nullable|string|max:120',
            'capacity' => 'nullable|integer|min:1|max:100000',
            'status' => ['required', Rule::in(['upcoming', 'ongoing', 'completed', 'cancelled'])],
        ]);
    }
}
