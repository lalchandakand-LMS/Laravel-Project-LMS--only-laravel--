<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
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
