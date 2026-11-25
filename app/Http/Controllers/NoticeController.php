<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function index(Request $request)
    {
        $notices = $this->visibleTo($request->user())
            ->with(['course', 'batch'])
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

        return view('notices.show', compact('announcement'));
    }

    private function visibleTo(User $user)
    {
        $query = Announcement::query()
            ->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($audience) => $audience->whereNull('audience_role')->orWhere('audience_role', $user->role)));

        if ($user->isAdmin()) return $query;

        return $query->where(function ($scope) use ($user) {
            $scope->where(function ($general) {
                $general->whereNull('course_id')->whereNull('batch_id');
            });

            if ($user->isStudent()) {
                $scope->orWhere(function ($target) use ($user) {
                    $target->whereNotNull('batch_id')
                        ->whereHas('batch.enrollments', fn ($e) => $e->where('user_id', $user->id)->where('status', 'active'));
                })->orWhere(function ($target) use ($user) {
                    $target->whereNotNull('course_id')->whereNull('batch_id')
                        ->whereHas('course.enrollments', fn ($e) => $e->where('user_id', $user->id)->where('status', 'active'));
                });
            } elseif ($user->isGuardian()) {
                $scope->orWhere(function ($target) use ($user) {
                    $childIds = $user->children()->select('users.id');
                    $target->whereNotNull('batch_id')
                        ->whereHas('batch.enrollments', fn ($e) => $e->whereIn('user_id', $childIds)->where('status', 'active'));
                })->orWhere(function ($target) use ($user) {
                    $childIds = $user->children()->select('users.id');
                    $target->whereNotNull('course_id')->whereNull('batch_id')
                        ->whereHas('course.enrollments', fn ($e) => $e->whereIn('user_id', $childIds)->where('status', 'active'));
                });
            } elseif ($user->isInstructor()) {
                $scope->orWhere(function ($target) use ($user) {
                    $target->whereNotNull('batch_id')->whereHas('batch', fn ($b) => $b->where('instructor_id', $user->id));
                })->orWhere(function ($target) use ($user) {
                    $target->whereNotNull('course_id')->whereNull('batch_id')
                        ->whereHas('course', fn ($c) => $c->where('instructor_id', $user->id));
                });
            }
        });
    }
}
