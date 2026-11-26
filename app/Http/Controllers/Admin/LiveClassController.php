<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LiveClass;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class LiveClassController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $classes = LiveClass::with('course')
            ->when(! $user->isAdmin(), fn ($q) => $q
                ->whereHas('course', fn ($c) => $c->where('instructor_id', $user->id)))
            ->when($request->get('course'), fn ($q, $id) => $q->where('course_id', $id))
            ->orderByDesc('starts_at')
            ->paginate(15)->withQueryString();

        return view('admin.live-classes.index', [
            'classes' => $classes,
            'courses' => $this->courses(),
        ]);
    }

    public function create()
    {
        return view('admin.live-classes.form', [
            'liveClass' => new LiveClass([
                'platform' => 'Zoom', 'starts_at' => now()->addDay()->startOfHour(),
                'duration_minutes' => 60, 'is_published' => true,
            ]),
            'courses' => $this->courses(),
            'platforms' => LiveClass::PLATFORMS,
        ]);
    }

    public function store(Request $request)
    {
        LiveClass::create($this->validated($request));

        return redirect()->route('admin.live-classes.index')->with('success', 'Live class scheduled.');
    }

    public function edit(LiveClass $liveClass)
    {
        $this->authorizeOwner($liveClass);

        return view('admin.live-classes.form', [
            'liveClass' => $liveClass,
            'courses' => $this->courses(),
            'platforms' => LiveClass::PLATFORMS,
        ]);
    }

    public function update(Request $request, LiveClass $liveClass)
    {
        $this->authorizeOwner($liveClass);
        $liveClass->update($this->validated($request));

        return redirect()->route('admin.live-classes.index')->with('success', 'Live class updated.');
    }

    public function destroy(LiveClass $liveClass)
    {
        $this->authorizeOwner($liveClass);
        $liveClass->delete();

        return redirect()->route('admin.live-classes.index')->with('success', 'Live class deleted.');
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

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'course_id' => 'required|exists:courses,id',
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
