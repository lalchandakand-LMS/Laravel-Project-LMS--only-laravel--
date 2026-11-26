<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NoticeController extends Controller
{
    public function index(Request $request)
    {
        $notices = $this->visibleTo($request->user())
            ->with(['course', 'batch', 'branch'])
            ->withCount('attachments')
            ->orderByDesc('is_pinned')->orderByDesc('publish_at')->orderByDesc('created_at')
            ->paginate(15);

        $readIds = $request->user()->getConnection()->table('announcement_reads')
            ->where('user_id', $request->user()->id)->pluck('announcement_id')->all();

        return view('notices.index', compact('notices', 'readIds'));
    }

    public function show(Request $request, Announcement $announcement)
    {
        abort_unless($this->visibleTo($request->user())->whereKey($announcement->id)->exists(), 404);
        $request->user()->getConnection()->table('announcement_reads')->updateOrInsert(
            ['announcement_id' => $announcement->id, 'user_id' => $request->user()->id],
            ['read_at' => now()]
        );

        $announcement->load('attachments', 'course', 'batch', 'branch');
        return view('notices.show', compact('announcement'));
    }

    public function attachment(Request $request, AnnouncementAttachment $attachment)
    {
        abort_unless(
            $this->visibleTo($request->user())->whereKey($attachment->announcement_id)->exists(),
            404
        );

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    private function visibleTo(User $user)
    {
        $query = Announcement::query()
            ->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($audience) => $audience->whereNull('audience_role')->orWhere('audience_role', $user->role)));

        if ($user->isAdmin()) return $query;

        $query->where(function ($branch) use ($user) {
            $branch->whereNull('branch_id');
            if ($user->isGuardian()) {
                $childIds = $user->children()->select('users.id');
                $branch->orWhereIn('branch_id', User::whereIn('id', $childIds)->whereNotNull('branch_id')->select('branch_id'));
            } elseif ($user->branch_id) {
                $branch->orWhere('branch_id', $user->branch_id);
            }
        });

        return $query->where(function ($scope) use ($user) {
            $scope->where(function ($general) use ($user) {
                $general->whereNull('course_id')->whereNull('batch_id')
                    ->where(function ($branch) use ($user) {
                        $branch->whereNull('branch_id');
                        if ($user->isGuardian()) {
                            $childIds = $user->children()->select('users.id');
                            $branch->orWhereIn('branch_id', User::whereIn('id', $childIds)->select('branch_id')->whereNotNull('branch_id'));
                        } else {
                            $branch->orWhere('branch_id', $user->branch_id);
                        }
                    });
            });

            if ($user->isStudent()) {
                $scope->orWhere(function ($target) use ($user) {
                    $target->whereNotNull('batch_id')->where(function ($q) use ($user) {
                        $q->whereHas('batch.enrollments', fn ($e) => $e->where('user_id', $user->id)->where('status', 'active'))
                            ->orWhereHas('batch.onlineEnrollments', fn ($e) => $e->where('student_id', $user->id)->where('status', 'active'));
                    });
                })->orWhere(function ($target) use ($user) {
                    $target->whereNotNull('course_id')->whereNull('batch_id')->where(function ($q) use ($user) {
                        $q->whereHas('course.enrollments', fn ($e) => $e->where('user_id', $user->id)->where('status', 'active'))
                            ->orWhereHas('course.batches.onlineEnrollments', fn ($e) => $e->where('student_id', $user->id)->where('status', 'active'));
                    });
                });
            } elseif ($user->isGuardian()) {
                $childIds = $user->children()->select('users.id');
                $scope->orWhere(function ($target) use ($childIds) {
                    $target->whereNotNull('batch_id')->where(function ($q) use ($childIds) {
                        $q->whereHas('batch.enrollments', fn ($e) => $e->whereIn('user_id', $childIds)->where('status', 'active'))
                            ->orWhereHas('batch.onlineEnrollments', fn ($e) => $e->whereIn('student_id', $childIds)->where('status', 'active'));
                    });
                })->orWhere(function ($target) use ($childIds) {
                    $target->whereNotNull('course_id')->whereNull('batch_id')->where(function ($q) use ($childIds) {
                        $q->whereHas('course.enrollments', fn ($e) => $e->whereIn('user_id', $childIds)->where('status', 'active'))
                            ->orWhereHas('course.batches.onlineEnrollments', fn ($e) => $e->whereIn('student_id', $childIds)->where('status', 'active'));
                    });
                });
            } elseif ($user->isInstructor()) {
                $scope->orWhere(function ($target) use ($user) {
                    $target->whereNotNull('batch_id')->whereHas('batch', fn ($b) => $b->where('instructor_id', $user->id));
                })->orWhere(function ($target) use ($user) {
                    $target->whereNotNull('course_id')->whereNull('batch_id')
                        ->whereHas('course', fn ($c) => $c->where('instructor_id', $user->id));
                });
            } elseif ($user->isBranchManager()) {
                $scope->orWhere(function ($target) use ($user) {
                    $target->where('branch_id', $user->branch_id)->whereNotNull('branch_id');
                });
            }
        });
    }
}
