@extends('layouts.admin')
@section('title', 'Staff Payroll')
@section('heading', 'Staff Payroll')
@section('content')
<div class="grid gap-6 xl:grid-cols-[360px_1fr]">
    <section class="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="mb-1 font-bold text-slate-900">Create monthly payroll</h3>
        <p class="mb-4 text-xs text-slate-500">New entries start as drafts. Approve before recording payment.</p>
        <form method="POST" action="{{ route('admin.staff-payroll.store') }}" class="space-y-3">
            @csrf
            <div><label class="mb-1 block text-sm font-semibold">Staff member</label><select id="payroll-staff" name="staff_user_id" required class="w-full rounded-lg border px-3 py-2 text-sm"><option value="">Select staff</option>@foreach ($staff as $member)<option value="{{ $member->id }}" data-base-salary="{{ $member->staffProfile?->base_salary ?? 0 }}" @selected(old('staff_user_id') == $member->id)>{{ $member->name }} — {{ $member->staffProfile?->employee_code ?? 'No ID' }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm font-semibold">Payroll month</label><input type="month" name="payroll_month" value="{{ old('payroll_month', now()->format('Y-m')) }}" required class="w-full rounded-lg border px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Basic salary (৳)</label><input id="payroll-basic-salary" type="number" name="basic_salary" min="0" step="0.01" value="{{ old('basic_salary') }}" required class="w-full rounded-lg border px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Allowances (৳)</label><input type="number" name="allowances" min="0" step="0.01" value="{{ old('allowances', 0) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Deductions (৳)</label><input type="number" name="deductions" min="0" step="0.01" value="{{ old('deductions', 0) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Notes</label><textarea name="notes" rows="2" class="w-full rounded-lg border px-3 py-2 text-sm">{{ old('notes') }}</textarea></div>
            <button class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Save payroll draft</button>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b p-4">
            <a href="{{ route('admin.staff-attendance.index') }}" class="rounded-lg border px-3 py-2 text-sm font-semibold">Staff Attendance</a>
            <div><label class="mb-1 block text-xs font-semibold">Month</label><input type="month" name="month" value="{{ request('month') }}" class="rounded-lg border px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-xs font-semibold">Status</label><select name="status" class="rounded-lg border px-3 py-2 text-sm"><option value="">All</option>@foreach (['draft','approved','paid'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button>
            <a href="{{ route('admin.staff-payroll.index') }}" class="px-2 py-2 text-sm text-slate-500">Reset</a>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Staff / Month</th><th class="px-4 py-3">Gross</th><th class="px-4 py-3">Deduction</th><th class="px-4 py-3">Net</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th></tr></thead>
                <tbody class="divide-y">
                @forelse ($payrolls as $payroll)
                    <tr>
                        <td class="px-4 py-3"><p class="font-semibold">{{ $payroll->staffProfile?->user?->name ?? 'Deleted staff' }}</p><p class="text-xs text-slate-500">{{ $payroll->staffProfile?->employee_code }} · {{ $payroll->payroll_month }}</p></td>
                        <td class="px-4 py-3">৳{{ number_format((float)$payroll->basic_salary + (float)$payroll->allowances, 2) }}</td>
                        <td class="px-4 py-3">৳{{ number_format((float)$payroll->deductions, 2) }}</td>
                        <td class="px-4 py-3 font-bold">৳{{ number_format((float)$payroll->net_salary, 2) }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-bold {{ $payroll->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($payroll->status === 'approved' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700') }}">{{ ucfirst($payroll->status) }}</span>@if($payroll->paid_at)<p class="mt-1 text-xs text-slate-500">{{ $payroll->paid_at->format('d M Y') }} · {{ $payroll->payment_method }}</p>@endif</td>
                        <td class="px-4 py-3"><div class="flex flex-wrap gap-2">
                            @if ($payroll->status === 'draft')
                                <form method="POST" action="{{ route('admin.staff-payroll.approve', $payroll) }}">@csrf<button class="text-xs font-bold text-sky-700">Approve</button></form>
                                <form method="POST" action="{{ route('admin.staff-payroll.destroy', $payroll) }}" onsubmit="return confirm('Delete this draft payroll?')">@csrf @method('DELETE')<button class="text-xs font-bold text-rose-700">Delete</button></form>
                            @elseif ($payroll->status === 'approved')
                                <form method="POST" action="{{ route('admin.staff-payroll.paid', $payroll) }}" class="flex flex-wrap items-center gap-1">@csrf<input type="date" name="paid_at" value="{{ now()->toDateString() }}" required class="rounded border px-1 py-1 text-xs"><select name="payment_method" class="rounded border px-1 py-1 text-xs"><option value="bank">Bank</option><option value="cash">Cash</option><option value="mobile_banking">Mobile banking</option><option value="cheque">Cheque</option></select><input name="payment_reference" placeholder="Reference" class="w-20 rounded border px-1 py-1 text-xs"><button class="text-xs font-bold text-emerald-700">Mark paid</button></form>
                            @else
                                <span class="text-xs text-slate-500">Paid by {{ $payroll->paidBy?->name ?? '—' }}</span>
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No payroll records found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $payrolls->links() }}</div>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const staff = document.getElementById('payroll-staff');
    const salary = document.getElementById('payroll-basic-salary');
    staff?.addEventListener('change', () => {
        const selected = staff.options[staff.selectedIndex];
        if (selected && salary && (!salary.value || Number(salary.value) === 0)) {
            salary.value = selected.dataset.baseSalary || '0';
        }
    });
});
</script>
@endsection
