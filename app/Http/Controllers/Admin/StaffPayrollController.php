<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffPayroll;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class StaffPayrollController extends Controller
{
    public function index(Request $request)
    {
        $payrolls = StaffPayroll::with('staffProfile.user', 'paidBy')
            ->when($request->filled('month'), fn ($query) => $query->where('payroll_month', $request->input('month')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest('payroll_month')->paginate(25)->withQueryString();

        $staff = User::query()->whereIn('role', ['instructor', 'branch_manager'])
            ->where('is_active', true)->with('staffProfile')->orderBy('name')->get();

        return view('admin.staff-payroll.index', compact('payrolls', 'staff'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'staff_user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereIn('role', ['instructor', 'branch_manager'])],
            'payroll_month' => ['required', 'date_format:Y-m'],
            'basic_salary' => 'required|numeric|min:0|max:99999999.99',
            'allowances' => 'nullable|numeric|min:0|max:99999999.99',
            'deductions' => 'nullable|numeric|min:0|max:99999999.99',
            'notes' => 'nullable|string|max:2000',
        ]);

        $staffUser = User::findOrFail($data['staff_user_id']);
        abort_unless($staffUser->is_active, 422, 'Inactive staff cannot be added to a new payroll.');

        $profile = $staffUser->staffProfile()->firstOrCreate([], ['employment_status' => 'active']);
        abort_unless($profile->employment_status === 'active', 422, 'Only active staff can receive a new payroll entry.');

        if (StaffPayroll::where('staff_profile_id', $profile->id)->where('payroll_month', $data['payroll_month'])->exists()) {
            throw ValidationException::withMessages(['payroll_month' => 'Payroll for this staff member and month already exists.']);
        }

        $basic = (float) $data['basic_salary'];
        $allowances = (float) ($data['allowances'] ?? 0);
        $deductions = (float) ($data['deductions'] ?? 0);
        if ($deductions > $basic + $allowances) {
            throw ValidationException::withMessages(['deductions' => 'Deductions cannot exceed gross salary.']);
        }

        StaffPayroll::create([
            'staff_profile_id' => $profile->id,
            'payroll_month' => $data['payroll_month'],
            'basic_salary' => $basic,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'net_salary' => $basic + $allowances - $deductions,
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Payroll draft created.');
    }

    public function approve(StaffPayroll $payroll)
    {
        abort_unless($payroll->status === 'draft', 409, 'Only draft payroll can be approved.');
        $payroll->update(['status' => 'approved']);

        return back()->with('success', 'Payroll approved.');
    }

    public function markPaid(Request $request, StaffPayroll $payroll)
    {
        $data = $request->validate([
            'paid_at' => 'required|date',
            'payment_method' => ['required', Rule::in(['bank', 'cash', 'mobile_banking', 'cheque'])],
            'payment_reference' => 'nullable|string|max:120',
        ]);

        DB::transaction(function () use ($payroll, $data, $request) {
            $locked = StaffPayroll::query()->lockForUpdate()->findOrFail($payroll->id);
            abort_unless($locked->status === 'approved', 409, 'Only approved payroll can be marked paid.');

            $locked->update([
                'status' => 'paid',
                'paid_at' => $data['paid_at'],
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'paid_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Payroll payment recorded.');
    }

    public function destroy(StaffPayroll $payroll)
    {
        abort_unless($payroll->status === 'draft', 409, 'Approved or paid payroll records cannot be deleted.');
        $payroll->delete();

        return back()->with('success', 'Draft payroll deleted.');
    }
}
