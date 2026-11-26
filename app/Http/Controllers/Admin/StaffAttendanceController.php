<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', today()->toDateString());
        validator(['date' => $date], ['date' => 'required|date'])->validate();

        $staff = User::query()->whereIn('role', ['instructor', 'branch_manager'])
            ->where('is_active', true)->with('branch', 'staffProfile')
            ->orderBy('name')->get();

        $records = StaffAttendance::with('staffProfile.user')
            ->whereDate('attendance_date', $date)->get()->keyBy('staff_profile_id');

        return view('admin.staff-attendance.index', compact('date', 'staff', 'records'));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'attendance_date' => 'required|date',
            'records' => 'required|array',
            'records.*.user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereIn('role', ['instructor', 'branch_manager'])],
            'records.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'leave', 'half_day', 'remote'])],
            'records.*.check_in' => 'nullable|date_format:H:i',
            'records.*.check_out' => 'nullable|date_format:H:i|after:records.*.check_in',
            'records.*.notes' => 'nullable|string|max:500',
        ]);

        foreach ($data['records'] as $record) {
            $user = User::whereKey($record['user_id'])->whereIn('role', ['instructor', 'branch_manager'])->firstOrFail();
            $profile = $user->staffProfile()->firstOrCreate([], ['employment_status' => 'active']);

            $attendance = StaffAttendance::where('staff_profile_id', $profile->id)
                ->whereDate('attendance_date', $data['attendance_date'])->first()
                ?? new StaffAttendance(['staff_profile_id' => $profile->id, 'attendance_date' => $data['attendance_date']]);

            $attendance->fill([
                'branch_id' => $user->branch_id,
                'status' => $record['status'],
                'check_in' => $record['check_in'] ?? null,
                'check_out' => $record['check_out'] ?? null,
                'notes' => $record['notes'] ?? null,
                'marked_by' => $request->user()->id,
            ])->save();
        }

        return redirect()->route('admin.staff-attendance.index', ['date' => $data['attendance_date']])
            ->with('success', 'Staff attendance saved.');
    }
}
