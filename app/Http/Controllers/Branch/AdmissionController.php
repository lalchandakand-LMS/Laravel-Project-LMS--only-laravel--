<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\CoachingAdmissionController as AdminCoachingAdmissionController;
use App\Http\Controllers\Admin\OnlineBatchApplicationController as AdminOnlineBatchApplicationController;
use App\Models\Branch;
use App\Models\CoachingAdmission;
use App\Models\OnlineBatchApplication;
use App\Models\User;
use Illuminate\Http\Request;

class AdmissionController extends Controller
{
    private function branch(): Branch
    {
        $user = auth()->user();
        $branch = $user->isBranchManager()
            ? Branch::forManager($user)
            : ($user->branch ?? null);

        abort_unless($branch, 403, 'No branch is assigned to this account.');
        if ($user->isBranchManager() && $user->branch_id && (int) $user->branch_id !== (int) $branch->id) {
            abort(403, 'Your branch assignment is inconsistent. Please contact the admin.');
        }

        abort_unless($branch->is_active, 403, 'This branch is closed to admission actions.');

        return $branch;
    }

    public function index(Request $request)
    {
        $branch = $this->branch();
        $onlineApplications = OnlineBatchApplication::with(['student', 'batch.course', 'reviewer'])
            ->whereHas('batch', fn ($batch) => $batch->where('branch_id', $branch->id))
            ->when($request->filled('online_status'), fn ($query) => $query->where('status', $request->input('online_status')))
            ->latest()->paginate(15, ['*'], 'online_page')->withQueryString();

        $coachingAdmissions = CoachingAdmission::with(['student', 'course', 'coachingBatch', 'invoices'])
            ->where('branch_id', $branch->id)
            ->whereHas('coachingBatch', fn ($batch) => $batch->where('branch_id', $branch->id))
            ->when($request->filled('coaching_status'), fn ($query) => $query->where('status', $request->input('coaching_status')))
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search
                ->where('student_name', 'like', '%'.$request->input('q').'%')
                ->orWhere('student_email', 'like', '%'.$request->input('q').'%')
                ->orWhere('application_no', 'like', '%'.$request->input('q').'%')))
            ->latest()->paginate(15, ['*'], 'coaching_page')->withQueryString();

        return view('branch.admissions.index', compact('branch', 'onlineApplications', 'coachingAdmissions'));
    }

    public function approveOnline(Request $request, OnlineBatchApplication $application)
    {
        $branch = $this->branch();
        abort_unless((int) $application->batch?->branch_id === (int) $branch->id, 403);
        abort_if($application->student?->branch_id && (int) $application->student->branch_id !== (int) $branch->id, 403, 'This student is assigned to another branch.');
        return app(AdminOnlineBatchApplicationController::class)->approve($request, $application);
    }

    public function rejectOnline(Request $request, OnlineBatchApplication $application)
    {
        $branch = $this->branch();
        abort_unless((int) $application->batch?->branch_id === (int) $branch->id, 403);
        return app(AdminOnlineBatchApplicationController::class)->reject($request, $application);
    }

    public function approveCoaching(Request $request, CoachingAdmission $admission)
    {
        $branch = $this->branch();
        abort_unless((int) $admission->branch_id === (int) $branch->id
            && (int) $admission->coachingBatch?->branch_id === (int) $branch->id, 403);
        $existingStudent = User::where('email', $admission->student_email)->first();
        abort_if($existingStudent?->branch_id && (int) $existingStudent->branch_id !== (int) $branch->id, 403, 'This student is already assigned to another branch.');
        return app(AdminCoachingAdmissionController::class)->approve($request, $admission);
    }

    public function rejectCoaching(Request $request, CoachingAdmission $admission)
    {
        $branch = $this->branch();
        abort_unless((int) $admission->branch_id === (int) $branch->id
            && (int) $admission->coachingBatch?->branch_id === (int) $branch->id, 403);
        return app(AdminCoachingAdmissionController::class)->reject($request, $admission);
    }
}
