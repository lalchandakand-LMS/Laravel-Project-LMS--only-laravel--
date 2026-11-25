<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use App\Notifications\AnnouncementPublishedNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends Controller
{
    public function index()
    {
        return view('admin.announcements.index', [
            'announcements' => Announcement::with(['course', 'batch'])->latest()->paginate(15),
        ]);
    }

    public function create()
    {
        return view('admin.announcements.form', [
            'announcement' => new Announcement(['is_published' => true, 'priority' => 'general']),
            'courses' => Course::orderBy('title')->get(),
            'batches' => Batch::whereNull('deleted_at')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $announcement = Announcement::create($this->validated($request));
        if ($this->isVisible($announcement)) $this->notifyAudience($announcement);
        return redirect()->route('admin.announcements.index')->with('success', 'Announcement posted.');
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.form', [
            'announcement' => $announcement,
            'courses' => Course::orderBy('title')->get(),
            'batches' => Batch::whereNull('deleted_at')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $wasVisible = $this->isVisible($announcement);
        $announcement->update($this->validated($request));
        if (! $wasVisible && $this->isVisible($announcement)) $this->notifyAudience($announcement);
        return redirect()->route('admin.announcements.index')->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return back()->with('success', 'Announcement deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'body' => 'required|string|max:30000',
            'course_id' => 'nullable|exists:courses,id',
            'batch_id' => 'nullable|exists:batches,id',
            'priority' => 'required|in:general,important,urgent',
            'audience_role' => 'nullable|in:student,guardian,instructor',
            'publish_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:publish_at',
        ]);
        $data['is_published'] = $request->boolean('is_published');
        $data['is_pinned'] = $request->boolean('is_pinned');

        if (! empty($data['batch_id'])) {
            $batch = Batch::findOrFail($data['batch_id']);
            if (! empty($data['course_id']) && (int) $data['course_id'] !== (int) $batch->course_id) {
                throw ValidationException::withMessages(['batch_id' => 'Selected batch does not belong to the selected course.']);
            }
            $data['course_id'] = $data['course_id'] ?: $batch->course_id;
        }

        return $data;
    }

    private function isVisible(Announcement $announcement): bool
    {
        return $announcement->is_published
            && (! $announcement->publish_at || $announcement->publish_at->isPast())
            && (! $announcement->expires_at || $announcement->expires_at->isFuture());
    }

    private function notifyAudience(Announcement $announcement): void
    {
        $roles = $announcement->audience_role
            ? [$announcement->audience_role]
            : ['student', 'guardian', 'instructor'];

        foreach ($roles as $role) {
            $users = User::where('is_active', true)->where('role', $role);

            if ($announcement->course_id) {
                if ($role === 'student') {
                    $users->whereHas('enrollments', fn ($q) => $q->where('course_id', $announcement->course_id)->where('status', 'active'));
                } elseif ($role === 'guardian') {
                    $users->whereHas('children.enrollments', fn ($q) => $q->where('course_id', $announcement->course_id)->where('status', 'active'));
                } else {
                    $users->whereHas('courses', fn ($q) => $q->whereKey($announcement->course_id));
                }
            }

            if ($announcement->batch_id) {
                if ($role === 'student') {
                    $users->whereHas('enrollments', fn ($q) => $q->where('batch_id', $announcement->batch_id)->where('status', 'active'));
                } elseif ($role === 'guardian') {
                    $users->whereHas('children.enrollments', fn ($q) => $q->where('batch_id', $announcement->batch_id)->where('status', 'active'));
                } else {
                    $instructorId = Batch::whereKey($announcement->batch_id)->value('instructor_id');
                    $users->whereKey($instructorId ?: 0);
                }
            }

            $users->chunkById(200, function ($recipients) use ($announcement) {
                foreach ($recipients as $recipient) {
                    $recipient->notify(new AnnouncementPublishedNotification($announcement));
                }
            });
        }
    }
}
