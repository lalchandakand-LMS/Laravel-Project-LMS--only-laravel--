<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachingAdmission;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CoachingAdmissionController extends Controller
{
    public function index(Request $request)
    {
        $admissions = CoachingAdmission::with(['branch', 'course', 'batch', 'student'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('student_name', 'like', '%'.$request->input('q').'%')->orWhere('student_email', 'like', '%'.$request->input('q').'%')->orWhere('application_no', 'like', '%'.$request->input('q').'%')))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.coaching-admissions.index', compact('admissions'));
    }

    public function approve(Request $request, CoachingAdmission $admission)
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($admission, $data) {
            $admission = CoachingAdmission::with(['batch', 'branch'])->lockForUpdate()->findOrFail($admission->id);
            abort_unless($admission->status === 'pending', 422, 'এই আবেদনটি আর pending অবস্থায় নেই।');
            $batch = $admission->batch()->lockForUpdate()->firstOrFail();
            abort_unless($batch->status === 'upcoming' || $batch->status === 'ongoing', 422, 'এই ব্যাচে ভর্তি বন্ধ।');
            if ($batch->capacity && $batch->activeStudentCount() >= $batch->capacity) {
                abort(422, 'এই ব্যাচের আসন পূর্ণ।');
            }

            $student = User::where('email', $admission->student_email)->lockForUpdate()->first();
            if ($student && $student->role !== 'student') {
                abort(422, 'এই ইমেইলটি অন্য user role-এ ব্যবহৃত। আগে account যাচাই করুন।');
            }
            if (! $student) {
                $student = User::create([
                    'name' => $admission->student_name,
                    'email' => $admission->student_email,
                    'phone' => $admission->student_phone,
                    'password' => Str::random(48),
                    'role' => 'student',
                    'branch_id' => $admission->branch_id,
                    'is_active' => true,
                ]);
            } else {
                $student->update(['phone' => $admission->student_phone, 'branch_id' => $admission->branch_id, 'is_active' => true]);
            }

            $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $admission->course_id)->lockForUpdate()->first();
            if ($enrollment && $enrollment->batch_id && (int) $enrollment->batch_id !== (int) $batch->id) {
                abort(422, 'শিক্ষার্থী ইতিমধ্যে এই কোর্সের অন্য ব্যাচে যুক্ত আছেন।');
            }
            if ($enrollment) {
                $enrollment->update(['batch_id' => $batch->id, 'status' => 'active']);
            } else {
                Enrollment::create([
                    'user_id' => $student->id, 'course_id' => $admission->course_id,
                    'batch_id' => $batch->id, 'status' => 'active',
                    'price_paid' => 0, 'enrolled_at' => now(),
                ]);
            }

            $admission->update([
                'student_id' => $student->id, 'status' => 'approved',
                'admin_note' => $data['admin_note'] ?? null,
                'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'admitted_at' => now(),
            ]);
        });

        return back()->with('success', 'ভর্তি অনুমোদিত হয়েছে এবং student account ও batch enrollment তৈরি হয়েছে।');
    }

    public function reject(Request $request, CoachingAdmission $admission)
    {
        $data = $request->validate(['admin_note' => ['required', 'string', 'max:2000']]);
        abort_unless($admission->status === 'pending', 422, 'এই আবেদনটি আর pending অবস্থায় নেই।');
        $admission->update([
            'status' => 'rejected', 'admin_note' => $data['admin_note'],
            'reviewed_by' => auth()->id(), 'reviewed_at' => now(),
        ]);
        return back()->with('success', 'ভর্তি আবেদনটি প্রত্যাখ্যান করা হয়েছে।');
    }
}