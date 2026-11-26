<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachingAdmission;
use App\Models\StudentFeeInvoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CoachingAdmissionController extends Controller
{
    public function index(Request $request)
    {
        $admissions = CoachingAdmission::with(['branch', 'course', 'coachingBatch', 'student', 'invoices', 'documents'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s
                ->where('student_name', 'like', '%'.$request->input('q').'%')
                ->orWhere('student_email', 'like', '%'.$request->input('q').'%')
                ->orWhere('application_no', 'like', '%'.$request->input('q').'%')))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.coaching-admissions.index', compact('admissions'));
    }

    public function approve(Request $request, CoachingAdmission $admission)
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($admission, $data) {
            $admission = CoachingAdmission::query()->lockForUpdate()->findOrFail($admission->id);
            abort_unless($admission->status === 'pending', 422, 'এই আবেদনটি আর pending অবস্থায় নেই।');
            abort_if($admission->documents()->where('status', '!=', 'approved')->exists(), 422, 'সব ভর্তি ডকুমেন্ট Admin দ্বারা যাচাই ও অনুমোদন করতে হবে।');

            $batch = $admission->coachingBatch()->lockForUpdate()->firstOrFail();
            abort_unless(in_array($batch->status, ['upcoming', 'ongoing'], true), 422, 'এই কোচিং ব্যাচে ভর্তি বন্ধ।');
            abort_unless($batch->hasAvailableSeat(), 422, 'এই কোচিং ব্যাচের আসন পূর্ণ।');

            $student = User::query()->where('email', $admission->student_email)->lockForUpdate()->first();
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

            $studentProfile = $student->studentProfile()->firstOrCreate([], ['status' => 'inactive']);
            $studentProfile->fill(array_filter([
                'date_of_birth' => $admission->student_date_of_birth,
                'gender' => $admission->student_gender,
                'school_name' => $admission->school_name,
                'academic_class' => $admission->academic_class,
                'academic_roll' => $admission->academic_roll,
                'academic_session' => $admission->academic_session,
                'academic_group' => $admission->academic_group,
                'present_address' => $admission->present_address,
                'permanent_address' => $admission->permanent_address,
                'guardian_name' => $admission->guardian_name,
                'guardian_phone' => $admission->guardian_phone,
                'guardian_email' => $admission->guardian_email,
                'emergency_contact_name' => $admission->guardian_name,
                'emergency_contact_phone' => $admission->guardian_phone,
                'quota_type' => $admission->quota_type,
                'quota_notes' => $admission->quota_notes,
            ], fn ($value) => $value !== null && $value !== ''))->save();

            $admission->documents()->get()->each(function ($document) use ($studentProfile) {
                $document->documentable()->associate($studentProfile);
                $document->save();
            });

            if ($admission->guardian_email && $admission->guardian_email !== $admission->student_email) {
                $guardian = User::query()->where('email', $admission->guardian_email)->lockForUpdate()->first();
                if ($guardian?->isGuardian()) {
                    $guardian->children()->syncWithoutDetaching([$student->id => ['relation' => 'guardian']]);
                }
            }

            $admission->update([
                'student_id' => $student->id,
                'status' => 'awaiting_payment',
                'admin_note' => $data['admin_note'] ?? null,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            if ((float) $batch->admission_fee > 0) {
                StudentFeeInvoice::firstOrCreate(
                    ['coaching_admission_id' => $admission->id, 'invoice_type' => 'admission'],
                    [
                        'student_id' => $student->id,
                        'coaching_batch_id' => $batch->id,
                        'course_id' => $admission->course_id,
                        'branch_id' => $admission->branch_id,
                        'description' => 'কোচিং ভর্তি ফি — '.$batch->name,
                        'issued_at' => now()->toDateString(),
                        'due_at' => now()->addDays(7)->toDateString(),
                        'amount' => $batch->admission_fee,
                        'discount' => 0,
                        'status' => 'unpaid',
                        'created_by' => auth()->id(),
                    ]
                );
            } else {
                $admission->activateAfterAdmissionFee();
            }
        });

        return back()->with('success', 'আবেদন অনুমোদিত হয়েছে। ভর্তি ফি থাকলে invoice তৈরি হয়েছে; ফি সম্পূর্ণ পরিশোধের পর কোচিং ব্যাচে ভর্তি সক্রিয় হবে।');
    }

    public function reject(Request $request, CoachingAdmission $admission)
    {
        $data = $request->validate(['admin_note' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($admission, $data, $request) {
            $lockedAdmission = CoachingAdmission::query()
                ->lockForUpdate()
                ->findOrFail($admission->id);

            abort_unless($lockedAdmission->status === 'pending', 422, 'এই আবেদনটি আর pending অবস্থায় নেই।');

            $lockedAdmission->update([
                'status' => 'rejected',
                'admin_note' => $data['admin_note'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return back()->with('success', 'ভর্তি আবেদনটি প্রত্যাখ্যান করা হয়েছে।');
    }
}
