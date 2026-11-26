<?php

namespace App\Http\Controllers;

use App\Models\CoachingAdmission;
use App\Models\CoachingBatch;
use Illuminate\Http\Request;

class CoachingAdmissionController extends Controller
{
    public function create()
    {
        return view('admissions.coaching', [
            'coachingBatches' => CoachingBatch::with(['branch', 'course'])
                ->whereIn('status', ['upcoming', 'ongoing'])
                ->orderBy('branch_id')->orderBy('starts_at')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_name' => ['required', 'string', 'max:160'],
            'student_email' => ['required', 'email', 'max:255'],
            'student_phone' => ['required', 'string', 'max:30'],
            'student_date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'student_gender' => ['nullable', 'in:male,female,other'],
            'school_name' => ['nullable', 'string', 'max:180'],
            'academic_class' => ['nullable', 'string', 'max:80'],
            'academic_roll' => ['nullable', 'string', 'max:60'],
            'academic_session' => ['nullable', 'string', 'max:40'],
            'academic_group' => ['nullable', 'string', 'max:80'],
            'present_address' => ['nullable', 'string', 'max:500'],
            'permanent_address' => ['nullable', 'string', 'max:500'],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'quota_type' => ['nullable', 'string', 'max:80'],
            'quota_notes' => ['nullable', 'string', 'max:1000'],
            'student_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'identity_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'quota_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'student_sms_consent' => ['sometimes', 'accepted'],
            'guardian_sms_consent' => ['sometimes', 'accepted'],
            'coaching_batch_id' => ['required', 'integer', 'exists:coaching_batches,id'],
            'applicant_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $batch = CoachingBatch::with(['branch', 'course'])->findOrFail($data['coaching_batch_id']);
        abort_unless(in_array($batch->status, ['upcoming', 'ongoing'], true), 422, 'এই কোচিং ব্যাচে আবেদন বন্ধ।');
        abort_unless($batch->branch?->is_active && $batch->course?->is_published, 422, 'এই শাখা বা কোর্সে বর্তমানে ভর্তি নেওয়া হচ্ছে না।');
        abort_unless($batch->hasAvailableSeat(), 422, 'এই কোচিং ব্যাচে আসন পূর্ণ হয়ে গেছে।');

        $duplicate = CoachingAdmission::where('student_email', $data['student_email'])
            ->where('coaching_batch_id', $batch->id)
            ->whereIn('status', ['pending', 'awaiting_payment', 'admitted'])->exists();
        abort_if($duplicate, 422, 'এই শিক্ষার্থীর এই কোচিং ব্যাচে আবেদন বা ভর্তি ইতিমধ্যে রয়েছে।');

        $uploadFields = ['student_photo', 'identity_document', 'quota_document'];
        foreach ($uploadFields as $field) {
            unset($data[$field]);
        }

        $admission = CoachingAdmission::create($data + [
            'branch_id' => $batch->branch_id,
            'course_id' => $batch->course_id,
            'student_sms_consent' => $request->boolean('student_sms_consent'),
            'guardian_sms_consent' => $request->boolean('guardian_sms_consent'),
            'status' => 'pending',
        ]);

        $documentTypes = [
            'student_photo' => 'photo',
            'identity_document' => 'birth_certificate',
            'quota_document' => 'other',
        ];

        foreach ($documentTypes as $field => $documentType) {
            if (! $request->hasFile($field)) {
                continue;
            }

            $file = $request->file($field);
            $path = $file->store('profile-documents/admissions/'.$admission->id, 'local');

            $admission->documents()->create([
                'document_type' => $documentType,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'status' => 'pending',
            ]);
        }

        return redirect()->route('coaching-admission.create')->with('success', 'আপনার সরাসরি কোচিংয়ের ভর্তি আবেদন জমা হয়েছে। যাচাই শেষে জানানো হবে।');
    }
}
