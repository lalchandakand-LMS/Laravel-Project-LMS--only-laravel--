<?php

namespace App\Http\Controllers;

use App\Models\CoachingAdmission;
use App\Models\StudentFeeInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CoachingFeeReportController extends Controller
{
    public function student()
    {
        $admissions = CoachingAdmission::with(['course', 'coachingBatch', 'branch', 'invoices.payments'])
            ->where('student_id', auth()->id())->whereIn('status', ['awaiting_payment', 'admitted'])->latest('admitted_at')->get();

        return view('coaching-fees.student', compact('admissions'));
    }

    public function guardian(User $child)
    {
        abort_unless(auth()->user()->isGuardian() && auth()->user()->children()->whereKey($child->id)->exists(), 403);
        $admissions = CoachingAdmission::with(['course', 'coachingBatch', 'branch', 'invoices.payments'])
            ->where('student_id', $child->id)->whereIn('status', ['awaiting_payment', 'admitted'])->latest('admitted_at')->get();

        return view('coaching-fees.guardian', compact('admissions', 'child'));
    }
}