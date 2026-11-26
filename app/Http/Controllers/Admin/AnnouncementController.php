<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\User;
use App\Notifications\AnnouncementPublishedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends Controller
{
    public function index()
    {
        return view('admin.announcements.index', [
            'announcements' => Announcement::with(['course', 'batch', 'branch'])
                ->withCount(['reads', 'deliveries', 'attachments'])
                ->latest()->paginate(15),
        ]);
    }

    public function create()
    {
        return view('admin.announcements.form', [
            'announcement' => new Announcement(['is_published' => true, 'priority' => 'general']),
            'courses' => Course::orderBy('title')->get(),
            'batches' => Batch::whereNull('deleted_at')->with('course')->orderBy('name')->get(),
            'branches' => Branch::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $announcement = Announcement::create($data);
        $this->storeAttachments($request, $announcement);
        if ($announcement->isVisible()) $this->notifyAudience($announcement);

        return redirect()->route('admin.announcements.index')->with('success', 'Announcement posted.');
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.form', [
            'announcement' => $announcement->load('attachments'),
            'courses' => Course::orderBy('title')->get(),
            'batches' => Batch::whereNull('deleted_at')->with('course')->orderBy('name')->get(),
            'branches' => Branch::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $announcement->update($this->validated($request));
        $this->removeAttachments($request, $announcement);
        $this->storeAttachments($request, $announcement);

        // Delivery records make this safe to run after every edit: existing
        // recipients are not spammed, while newly targeted users are notified.
        if ($announcement->fresh()->isVisible()) $this->notifyAudience($announcement->fresh());

        return redirect()->route('admin.announcements.index')->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement)
    {
        foreach ($announcement->attachments as $attachment) {
            Storage::disk('local')->delete($attachment->path);
            $attachment->delete();
        }
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
            'branch_id' => 'nullable|exists:branches,id',
            'priority' => 'required|in:general,important,urgent',
            'audience_role' => 'nullable|in:student,guardian,instructor,branch_manager',
            'publish_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:publish_at',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,webp|max:10240',
            'remove_attachments' => 'nullable|array',
            'remove_attachments.*' => 'integer',
        ]);

        $data['is_published'] = $request->boolean('is_published');
        $data['is_pinned'] = $request->boolean('is_pinned');

        if (! empty($data['batch_id'])) {
            $batch = Batch::findOrFail($data['batch_id']);
            if (! empty($data['course_id']) && (int) $data['course_id'] !== (int) $batch->course_id) {
                throw ValidationException::withMessages(['batch_id' => 'Selected batch does not belong to the selected course.']);
            }
            $data['course_id'] = $data['course_id'] ?: $batch->course_id;
            if (! empty($data['branch_id']) && $batch->branch_id && (int) $data['branch_id'] !== (int) $batch->branch_id) {
                throw ValidationException::withMessages(['branch_id' => 'Selected batch belongs to another branch.']);
            }
            $data['branch_id'] = $data['branch_id'] ?: $batch->branch_id;
        }

        unset($data['attachments'], $data['remove_attachments']);
        return $data;
    }

    private function storeAttachments(Request $request, Announcement $announcement): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('announcements/'.$announcement->id, 'local');
            $announcement->attachments()->create([
                'uploaded_by' => $request->user()->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    private function removeAttachments(Request $request, Announcement $announcement): void
    {
        foreach ($request->input('remove_attachments', []) as $id) {
            $attachment = $announcement->attachments()->whereKey($id)->first();
            if ($attachment) {
                Storage::disk('local')->delete($attachment->path);
                $attachment->delete();
            }
        }
    }

    private function notifyAudience(Announcement $announcement): void
    {
        $roles = $announcement->audience_role
            ? [$announcement->audience_role]
            : ['student', 'guardian', 'instructor', 'branch_manager'];

        foreach ($roles as $role) {
            $users = User::query()->where('is_active', true)->where('role', $role);

            if ($announcement->branch_id) {
                if ($role === 'guardian') {
                    $users->whereHas('children', fn ($q) => $q->where('branch_id', $announcement->branch_id));
                } else {
                    $users->where('branch_id', $announcement->branch_id);
                }
            }

            if ($announcement->course_id) {
                if ($role === 'student') {
                    $users->where(function ($q) use ($announcement) {
                        $q->whereHas('enrollments', fn ($e) => $e->where('course_id', $announcement->course_id)->where('status', 'active'))
                            ->orWhereHas('onlineBatchEnrollments', fn ($e) => $e->where('course_id', $announcement->course_id)->where('status', 'active'));
                    });
                } elseif ($role === 'guardian') {
                    $users->whereHas('children', fn ($child) => $child->whereHas('enrollments', fn ($e) => $e->where('course_id', $announcement->course_id)->where('status', 'active')));
                } elseif ($role === 'instructor') {
                    $users->whereHas('courses', fn ($q) => $q->whereKey($announcement->course_id));
                }
            }

            if ($announcement->batch_id) {
                if ($role === 'student') {
                    $users->where(function ($q) use ($announcement) {
                        $q->whereHas('enrollments', fn ($e) => $e->where('batch_id', $announcement->batch_id)->where('status', 'active'))
                            ->orWhereHas('onlineBatchEnrollments', fn ($e) => $e->where('batch_id', $announcement->batch_id)->where('status', 'active'));
                    });
                } elseif ($role === 'guardian') {
                    $users->whereHas('children', fn ($child) => $child->where(function ($q) use ($announcement) {
                        $q->whereHas('enrollments', fn ($e) => $e->where('batch_id', $announcement->batch_id)->where('status', 'active'))
                            ->orWhereHas('onlineBatchEnrollments', fn ($e) => $e->where('batch_id', $announcement->batch_id)->where('status', 'active'));
                    }));
                } elseif ($role === 'instructor') {
                    $users->whereKey(Batch::whereKey($announcement->batch_id)->value('instructor_id') ?: 0);
                }
            }

            $users->select('users.*')->chunkById(200, function ($recipients) use ($announcement) {
                foreach ($recipients as $recipient) {
                    DB::transaction(function () use ($recipient, $announcement) {
                        $exists = DB::table('announcement_deliveries')
                            ->where('announcement_id', $announcement->id)->where('user_id', $recipient->id)->exists();
                        if ($exists) return;

                        $recipient->notify(new AnnouncementPublishedNotification($announcement));
                        DB::table('announcement_deliveries')->insert([
                            'announcement_id' => $announcement->id,
                            'user_id' => $recipient->id,
                            'notified_at' => now(),
                        ]);
                    });
                }
            });
        }
    }
}
