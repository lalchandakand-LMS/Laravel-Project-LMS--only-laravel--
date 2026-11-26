<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnrollmentEligibility
{
    /**
     * Current course enrollments only: active, unexpired, and assigned to a
     * batch that is running and belongs to the student's branch.
     */
    public static function query(User $user): HasMany
    {
        return $user->enrollments()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(function ($batch) use ($user) {
                $batch->whereNull('batch_id')
                    ->orWhereHas('batch', function ($eligibleBatch) use ($user) {
                        $eligibleBatch->whereNotIn('status', ['upcoming', 'cancelled'])
                            ->where(function ($branch) use ($user) {
                                $branch->whereNull('branch_id')
                                    ->orWhere('branch_id', $user->branch_id);
                            });
                    });
            });
    }

    public static function forCourse(User $user, Course $course): ?Enrollment
    {
        return self::query($user)
            ->with('batch')
            ->where('course_id', $course->id)
            ->first();
    }

    public static function hasCourse(User $user, Course $course): bool
    {
        return self::query($user)->where('course_id', $course->id)->exists();
    }
}
