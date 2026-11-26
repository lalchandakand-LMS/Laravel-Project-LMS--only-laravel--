<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Branch as BranchModel;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    private function branch(): BranchModel
    {
        $user = auth()->user();
        $branch = $user->isBranchManager()
            ? BranchModel::forManager($user)
            : ($user->branch ?? null);

        abort_unless($branch, 403, 'No branch is assigned to this account.');
        if ($user->isBranchManager() && $user->branch_id && (int) $user->branch_id !== (int) $branch->id) {
            abort(403, 'Your branch assignment is inconsistent. Please contact the admin.');
        }

        return $branch;
    }

    public function index(Request $request)
    {
        $branch = $this->branch();
        $sessions = AttendanceSession::withCount('records')
            ->with(['batch', 'coachingBatch', 'course', 'instructor', 'reviewer'])
            ->where('branch_id', $branch->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest('starts_at')->paginate(20)->withQueryString();

        return view('branch.attendance.index', compact('branch', 'sessions'));
    }

    public function show(AttendanceSession $session)
    {
        $branch = $this->branch();
        abort_unless((int) $session->branch_id === (int) $branch->id, 404);

        $session->load(['batch', 'coachingBatch', 'course', 'instructor', 'reviewer', 'records.student']);

        return view('branch.attendance.show', compact('branch', 'session'));
    }

    public function review(Request $request, AttendanceSession $session)
    {
        $branch = $this->branch();
        abort_unless((int) $session->branch_id === (int) $branch->id, 404);
        abort_unless($session->status === 'submitted', 422, 'Only submitted attendance can be reviewed.');

        return app(\App\Http\Controllers\AttendanceController::class)->review($request, $session);
    }
}
