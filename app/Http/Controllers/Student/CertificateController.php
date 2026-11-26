<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;

class CertificateController extends Controller
{
    public function show(Course $course)
    {
        $user = auth()->user();
        $certificate = Certificate::where('user_id', $user->id)->where('course_id', $course->id)->first();

        // Keep already-issued certificates available, but do not mint a new one
        // from an expired enrollment or an ineligible batch/branch assignment.
        $enrollment = $user->enrollments()
            ->with('batch')
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        $eligible = (bool) $enrollment;
        if ($eligible && $enrollment->batch_id) {
            $batch = $enrollment->batch;
            $eligible = $batch
                && ! in_array($batch->status, ['upcoming', 'cancelled'], true)
                && (! $batch->branch_id || (int) $user->branch_id === (int) $batch->branch_id);
        }

        abort_unless($certificate || $eligible, 403, 'An active, eligible course enrollment is required.');

        abort_unless(
            $certificate || $course->progressFor($user) >= 100,
            403,
            'Finish the course to unlock your certificate.'
        );

        $certificate ??= Certificate::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'issued_at' => now(),
        ]);

        return view('student.certificate', compact('certificate', 'course'));
    }
}
