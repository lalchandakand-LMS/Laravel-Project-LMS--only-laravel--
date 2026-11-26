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
        abort_unless($certificate || $course->has_certificate, 404, 'Certificates are not enabled for this course.');

        // Keep already-issued certificates available, but do not mint a new one
        // from an expired enrollment or an ineligible batch/branch assignment.
        $eligible = \App\Services\EnrollmentEligibility::hasCourse($user, $course);

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
