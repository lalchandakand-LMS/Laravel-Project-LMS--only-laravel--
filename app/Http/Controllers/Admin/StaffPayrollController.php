<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffPayroll;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
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

    public function report(Request $request)
    {
        $filters = $this->reportFilters($request);
        $query = $this->filteredPayrolls($filters);

        $summary = (clone $query)->selectRaw('COUNT(*) as records, COALESCE(SUM(basic_salary), 0) as basic_total, COALESCE(SUM(allowances), 0) as allowance_total, COALESCE(SUM(deductions), 0) as deduction_total, COALESCE(SUM(net_salary), 0) as net_total')
            ->first();

        $monthly = (clone $query)->selectRaw("payroll_month, COUNT(*) as records, COALESCE(SUM(net_salary), 0) as net_total, COALESCE(SUM(deductions), 0) as deduction_total")
            ->groupBy('payroll_month')->orderBy('payroll_month')->get();

        $byStaff = (clone $query)->join('staff_profiles', 'staff_payrolls.staff_profile_id', '=', 'staff_profiles.id')
            ->join('users', 'staff_profiles.user_id', '=', 'users.id')
            ->selectRaw('users.id as user_id, users.name, staff_profiles.employee_code, COUNT(*) as records, COALESCE(SUM(staff_payrolls.net_salary), 0) as net_total')
            ->groupBy('users.id', 'users.name', 'staff_profiles.employee_code')
            ->orderBy('users.name')->get();

        $branches = \App\Models\Branch::orderBy('name')->get();

        return view('admin.staff-payroll.report', compact('filters', 'summary', 'monthly', 'byStaff', 'branches'));
    }

    public function exportReport(Request $request)
    {
        $filters = $this->reportFilters($request);
        $rows = $this->filteredPayrolls($filters)->with('staffProfile.user.branch')->orderBy('payroll_month')->get();
        $filename = 'staff-payroll-report-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Staff', 'Employee ID', 'Branch', 'Month', 'Basic Salary', 'Allowances', 'Deductions', 'Net Salary', 'Status', 'Paid At', 'Payment Method', 'Payment Reference']);
            foreach ($rows as $payroll) {
                fputcsv($output, [
                    $payroll->staffProfile?->user?->name ?? 'Deleted staff',
                    $payroll->staffProfile?->employee_code,
                    $payroll->staffProfile?->user?->branch?->name,
                    $payroll->payroll_month,
                    $payroll->basic_salary,
                    $payroll->allowances,
                    $payroll->deductions,
                    $payroll->net_salary,
                    $payroll->status,
                    $payroll->paid_at?->format('Y-m-d'),
                    $payroll->payment_method,
                    $payroll->payment_reference,
                ]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function reportFilters(Request $request): array
    {
        return $request->validate([
            'start_month' => ['nullable', 'date_format:Y-m'],
            'end_month' => ['nullable', 'date_format:Y-m', 'after_or_equal:start_month'],
            'status' => ['nullable', Rule::in(['draft', 'approved', 'paid'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
    }

    private function filteredPayrolls(array $filters)
    {
        return StaffPayroll::query()
            ->when($filters['start_month'] ?? null, fn ($query, $month) => $query->where('payroll_month', '>=', $month))
            ->when($filters['end_month'] ?? null, fn ($query, $month) => $query->where('payroll_month', '<=', $month))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['branch_id'] ?? null, fn ($query, $branch) => $query->whereHas('staffProfile.user', fn ($user) => $user->where('branch_id', $branch)));
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

        $basic = (float) $data['basic_salary'];
        $allowances = (float) ($data['allowances'] ?? 0);
        $deductions = (float) ($data['deductions'] ?? 0);
        if ($deductions > $basic + $allowances) {
            throw ValidationException::withMessages(['deductions' => 'Deductions cannot exceed gross salary.']);
        }

        try {
            DB::transaction(function () use ($data, $basic, $allowances, $deductions) {
                // Serialize payroll creation for the same staff member so two
                // concurrent requests cannot both pass the monthly duplicate check.
                $staffUser = User::query()->lockForUpdate()->findOrFail($data['staff_user_id']);
                if (! $staffUser->is_active) {
                    throw ValidationException::withMessages([
                        'staff_user_id' => 'Inactive staff cannot be added to a new payroll.',
                    ]);
                }

                $profile = $staffUser->staffProfile()->firstOrCreate([], ['employment_status' => 'active']);
                $profile = StaffProfile::query()->lockForUpdate()->findOrFail($profile->id);
                if ($profile->employment_status !== 'active') {
                    throw ValidationException::withMessages([
                        'staff_user_id' => 'Only active staff can receive a new payroll entry.',
                    ]);
                }

                if (StaffPayroll::where('staff_profile_id', $profile->id)
                    ->where('payroll_month', $data['payroll_month'])->exists()) {
                    throw ValidationException::withMessages(['payroll_month' => 'Payroll for this staff member and month already exists.']);
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
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicatePayrollConflict($exception)) {
                throw ValidationException::withMessages(['payroll_month' => 'Payroll for this staff member and month already exists.']);
            }

            throw $exception;
        }

        return back()->with('success', 'Payroll draft created.');
    }

    public function approve(StaffPayroll $payroll)
    {
        DB::transaction(function () use ($payroll) {
            $locked = StaffPayroll::query()->lockForUpdate()->findOrFail($payroll->id);
            abort_unless($locked->status === 'draft', 409, 'Only draft payroll can be approved.');
            $locked->update(['status' => 'approved']);
        });

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
        DB::transaction(function () use ($payroll) {
            $locked = StaffPayroll::query()->lockForUpdate()->findOrFail($payroll->id);
            abort_unless($locked->status === 'draft', 409, 'Approved or paid payroll records cannot be deleted.');
            $locked->delete();
        });

        return back()->with('success', 'Draft payroll deleted.');
    }

    private function isDuplicatePayrollConflict(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'staff_payrolls_staff_profile_id_payroll_month_unique')
            || (str_contains($message, 'staff_payrolls.staff_profile_id') && str_contains($message, 'payroll_month'));
    }
}
