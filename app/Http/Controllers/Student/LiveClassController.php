<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\LiveClass;
use App\Models\OnlineBatchEnrollment;
use App\Services\EnrollmentEligibility;

class LiveClassController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isStudent() || $user->isAdmin(), 403);

        $query = LiveClass::query()
            ->with(['course', 'batch'])
            ->where('is_published', true)
            ->whereNotNull('starts_at')
            ->whereHas('course', fn ($course) => $course->where('is_published', true));

        if (! $user->isAdmin()) {
            $legacyCourseIds = EnrollmentEligibility::query($user)->pluck('course_id');
            $onlineEnrollments = OnlineBatchEnrollment::query()
                ->where('student_id', $user->id)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->whereHas('batch', function ($batch) use ($user) {
                    $batch->whereNotIn('status', ['upcoming', 'cancelled'])
                        ->where(fn ($branch) => $branch->whereNull('branch_id')
                            ->orWhere('branch_id', $user->branch_id));
                });
            $eligibleCourseIds = $legacyCourseIds->merge($onlineEnrollments->pluck('course_id'))->unique();

            $query->whereIn('course_id', $eligibleCourseIds)
                ->where(function ($access) use ($user) {
                    $access->whereNull('batch_id')
                        ->orWhereHas('batch', function ($batch) use ($user) {
                            $batch->whereNotIn('status', ['upcoming', 'cancelled'])
                                ->where(fn ($branch) => $branch->whereNull('branch_id')
                                    ->orWhere('branch_id', $user->branch_id))
                                ->where(function ($membership) use ($user) {
                                    $membership->whereHas('enrollments', function ($enrollment) use ($user) {
                                        $enrollment->where('user_id', $user->id)
                                            ->where('status', 'active')
                                            ->where(fn ($expiry) => $expiry->whereNull('expires_at')
                                                ->orWhere('expires_at', '>', now()));
                                    })->orWhereHas('onlineEnrollments', function ($enrollment) use ($user) {
                                        $enrollment->where('student_id', $user->id)
                                            ->where('status', 'active')
                                            ->whereNull('ended_at');
                                    });
                                });
                        });
                });
        }

        $classes = $query
            ->orderByRaw('CASE WHEN starts_at >= ? THEN 0 ELSE 1 END', [now()])
            ->orderByRaw('CASE WHEN starts_at >= ? THEN starts_at END ASC', [now()])
            ->orderByRaw('CASE WHEN starts_at < ? THEN starts_at END DESC', [now()])
            ->paginate(12);

        return view('student.live-classes', compact('classes'));
    }
}
