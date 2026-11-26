<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $query = Certificate::with(['user', 'course'])->latest('issued_at');
        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($term) {
                $builder->where('code', 'like', '%'.$term.'%')
                    ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.$term.'%'))
                    ->orWhereHas('course', fn ($courses) => $courses->where('title', 'like', '%'.$term.'%'));
            });
        }

        return view('admin.certificates.index', [
            'certificates' => $query->paginate(20)->withQueryString(),
            'search' => $request->query('q', ''),
            'students' => User::where('role', 'student')->orderBy('name')->get(['id', 'name', 'email']),
            'courses' => Course::where('has_certificate', true)->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
        ]);
        $student = User::where('role', 'student')->findOrFail($data['user_id']);
        $course = Course::findOrFail($data['course_id']);
        abort_unless($course->has_certificate, 422, 'Certificates are not enabled for this course.');
        abort_unless(EnrollmentEligibility::hasCourse($student, $course), 422, 'The student does not have an active eligible enrollment.');
        abort_unless($course->progressFor($student) >= 100, 422, 'The student must complete the course before a certificate can be issued.');

        Certificate::firstOrCreate(['user_id' => $student->id, 'course_id' => $course->id], ['issued_at' => now()]);

        return redirect()->route('admin.certificates.index')->with('success', 'Certificate issued or existing certificate reused.');
    }
}
