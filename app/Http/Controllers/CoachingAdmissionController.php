<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\CoachingAdmission;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoachingAdmissionController extends Controller
{
    public function create()
    {
        return view('admissions.coaching', [
            'branches' => Branch::active()->orderBy('name')->get(),
            'courses' => Course::published()->orderBy('title')->get(),
            'batches' => Batch::whereIn('status', ['upcoming', 'ongoing'])->with(['branch', 'course'])->orderBy('starts_at')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_name' => ['required', 'string', 'max:160'],
            'student_email' => ['required', 'email', 'max:255'],
            'student_phone' => ['required', 'string', 'max:30'],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_email' => ['nullable', 'email', 'max:255'],\n            'student_sms_consent' => ['sometimes', 'accepted'],\n            'guardian_sms_consent' => ['sometimes', 'accepted'],
            'batch_id' => ['required', 'integer', Rule::exists('batches', 'id')->where(fn ($q) => $q->whereIn('status', ['upcoming', 'ongoing']))],
            'applicant_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $batch = Batch::with(['branch', 'course'])->findOrFail($data['batch_id']);
        if (! $batch->branch_id || ! $batch->course_id || ! $batch->branch?->is_active || ! $batch->course?->is_published) {
            return back()->withInput()->withErrors(['batch_id' => 'এই ব্যাচে বর্তমানে অনলাইন ভর্তি নেওয়া হচ্ছে না।']);
        }
        if ($batch->capacity && $batch->activeStudentCount() >= $batch->capacity) {
            return back()->withInput()->withErrors(['batch_id' => 'এই ব্যাচে আসন পূর্ণ হয়ে গেছে।']);
        }
        $pending = CoachingAdmission::where('student_email', $data['student_email'])->where('batch_id', $batch->id)->where('status', 'pending')->exists();
        if ($pending) {
            return back()->withInput()->withErrors(['student_email' => 'এই ইমেইল দিয়ে এই ব্যাচে একটি আবেদন ইতিমধ্যে জমা হয়েছে।']);
        }

        CoachingAdmission::create($data + [
            'branch_id' => $batch->branch_id,
            'course_id' => $batch->course_id,
            'student_sms_consent' => $request->boolean('student_sms_consent'),\n            'guardian_sms_consent' => $request->boolean('guardian_sms_consent'),\n            'status' => 'pending',
        ]);

        return redirect()->route('coaching-admission.create')->with('success', 'আপনার ভর্তি আবেদন জমা হয়েছে। যাচাই শেষে SMS/ফোনে জানানো হবে।');
    }
}