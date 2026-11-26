<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffLeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = StaffLeaveRequest::with(['user', 'reviewer'])
            ->when($request->get('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.leave-requests.index', compact('requests'));
    }

    public function approve(Request $request, StaffLeaveRequest $leaveRequest)
    {
        $data = $request->validate(['reviewer_note' => 'nullable|string|max:2000']);

        DB::transaction(function () use ($leaveRequest, $data) {
            $locked = StaffLeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== 'pending', 409, 'This leave request has already been reviewed.');

            $overlap = StaffLeaveRequest::where('user_id', $locked->user_id)
                ->whereKeyNot($locked->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $locked->end_date)
                ->whereDate('end_date', '>=', $locked->start_date)
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'leave_request' => 'This request overlaps another approved leave period for the same staff member.',
                ]);
            }

            $locked->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'reviewer_note' => $data['reviewer_note'] ?? null,
            ]);
        });

        return back()->with('success', 'Leave request approved.');
    }

    public function reject(Request $request, StaffLeaveRequest $leaveRequest)
    {
        $data = $request->validate(['reviewer_note' => 'nullable|string|max:2000']);

        DB::transaction(function () use ($leaveRequest, $data) {
            $locked = StaffLeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== 'pending', 409, 'This leave request has already been reviewed.');

            $locked->update([
                'status' => 'rejected',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'reviewer_note' => $data['reviewer_note'] ?? null,
            ]);
        });

        return back()->with('success', 'Leave request rejected.');
    }
}
