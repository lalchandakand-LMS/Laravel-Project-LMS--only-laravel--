<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('admin.reports.index', [
            'summary' => [
                'students' => User::where('role', 'student')->count(),
                'teachers' => User::where('role', 'instructor')->count(),
                'courses' => Course::count(),
                'batches' => Batch::count(),
                'enrollments' => Enrollment::count(),
                'certificates' => Certificate::count(),
                'paid_revenue' => (float) Order::where('status', 'paid')->sum('total'),
                'exam_attempts' => ExamAttempt::where('status', 'submitted')->count(),
                'average_score' => round((float) ExamAttempt::where('status', 'submitted')->avg('score'), 1),
            ],
        ]);
    }
}
