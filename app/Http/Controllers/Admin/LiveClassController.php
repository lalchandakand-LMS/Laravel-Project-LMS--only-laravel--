<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\LiveClass;
use App\Models\User;
use App\Notifications\LiveClassUpdateNotification;
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
        $liveClass = LiveClass::create($this->validated($request));
        if ($liveClass->is_published) {
            $this->notifyAudience($liveClass, 'scheduled', (int) $request->user()->id);
        }

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
        $wasPublished = $liveClass->is_published;
        $oldDetails = $liveClass->only(['course_id', 'batch_id', 'title', 'description', 'meeting_url', 'platform', 'starts_at', 'duration_minutes']);
        $liveClass->update($this->validated($request));

        if ($wasPublished && ! $liveClass->is_published) {
            $this->notifyAudience($liveClass, 'cancelled', (int) $request->user()->id);
        } elseif ($liveClass->is_published) {
            $newDetails = $liveClass->only(array_keys($oldDetails));
            $event = $wasPublished ? 'rescheduled' : 'scheduled';
            if (! $wasPublished || $oldDetails != $newDetails) {
                $this->notifyAudience($liveClass, $event, (int) $request->user()->id);
            }
        }

        return redirect()->route('admin.live-classes.index')->with('success', 'Live class updated.');
    }

    public function destroy(Request $request, LiveClass $liveClass)
    {
        $this->authorizeOwner($liveClass);
        if ($liveClass->is_published) {
            $this->notifyAudience($liveClass, 'cancelled', (int) $request->user()->id);
        }
        $liveClass->delete();

        return redirect()->route('admin.live-classes.index')->with('success', 'Live class cancelled and removed.');
    }

    /**
     * Notify only active learners who are eligible for this course or selected batch,
     * plus their linked guardians and the course instructor.
     */
    private function notifyAudience(LiveClass $liveClass, string $event, int $actorId): void
    {
        $studentQuery = User::query()
            ->where('role', 'student')
            ->where('is_active', true)
            ->where(function ($eligible) use ($liveClass) {
                $eligible->whereHas('enrollments', function ($enrollment) use ($liveClass) {
                    $enrollment->where('course_id', $liveClass->course_id)
                        ->where('status', 'active')
                        ->where(fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                    if ($liveClass->batch_id) $enrollment->where('batch_id', $liveClass->batch_id);
                })->orWhereHas('onlineBatchEnrollments', function ($enrollment) use ($liveClass) {
                    $enrollment->where('course_id', $liveClass->course_id)
                        ->where('status', 'active')
                        ->whereNull('ended_at');
                    if ($liveClass->batch_id) $enrollment->where('batch_id', $liveClass->batch_id);
                });
            });

        $studentQuery->chunkById(200, function ($students) use ($liveClass, $event) {
            foreach ($students as $student) {
                $student->notify(new LiveClassUpdateNotification($liveClass, $event));
            }

            $studentIds = $students->modelKeys();
            if ($studentIds !== []) {
                User::query()->where('role', 'guardian')->where('is_active', true)
                    ->whereHas('children', fn ($children) => $children->whereIn('users.id', $studentIds))
                    ->get()->each(fn (User $guardian) => $guardian->notify(new LiveClassUpdateNotification($liveClass, $event)));
            }
        });

        $instructorId = $liveClass->course?->instructor_id;
        if ($instructorId && $instructorId !== $actorId) {
            User::find($instructorId)?->notify(new LiveClassUpdateNotification($liveClass, $event));
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
