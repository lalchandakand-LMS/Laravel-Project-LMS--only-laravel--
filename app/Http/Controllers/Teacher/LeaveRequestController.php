<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\StaffLeaveRequest;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function index()
    {
        $requests = auth()->user()->staffLeaveRequests()
            ->with('reviewer')->latest()->paginate(15);

        return view('teacher.leave-requests.index', compact('requests'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'leave_type' => 'required|in:'.implode(',', array_keys(StaffLeaveRequest::TYPES)),
            'reason' => 'required|string|max:2000',
        ]);

        $overlap = auth()->user()->staffLeaveRequests()
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();

        if ($overlap) {
            return back()->withErrors(['start_date' => 'These dates overlap another pending or approved leave request.'])->withInput();
        }

        auth()->user()->staffLeaveRequests()->create($data + ['status' => 'pending']);

        return redirect()->route('teacher.leave-requests.index')->with('success', 'Leave request submitted for approval.');
    }

    public function cancel(StaffLeaveRequest $leaveRequest)
    {
        abort_unless((int) $leaveRequest->user_id === (int) auth()->id(), 404);
        abort_if($leaveRequest->status !== 'pending', 409, 'Only pending leave requests can be cancelled.');

        $leaveRequest->update(['status' => 'cancelled']);

        return back()->with('success', 'Leave request cancelled.');
    }
}
