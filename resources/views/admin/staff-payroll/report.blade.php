@extends('layouts.admin')
@section('title', 'Staff Payroll Reports')
@section('heading', 'Staff Payroll Reports')
@section('actions')
    <a href="{{ route('admin.staff-payroll.index') }}" class="rounded-lg border px-4 py-2 text-sm font-bold">Back to Payroll</a>
@endsection
@section('content')
<div class="space-y-6">
    <form method="GET" class="grid gap-3 rounded-2xl border bg-white p-4 shadow-sm sm:grid-cols-2 xl:grid-cols-5">
        <div><label class="mb-1 block text-xs font-semibold">From month</label><input type="month" name="start_month" value="{{ $filters['start_month'] ?? '' }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
        <div><label class="mb-1 block text-xs font-semibold">To month</label><input type="month" name="end_month" value="{{ $filters['end_month'] ?? '' }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
        <div><label class="mb-1 block text-xs font-semibold">Branch</label><select name="branch_id" class="w-full rounded-lg border px-3 py-2 text-sm"><option value="">All branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
        <div><label class="mb-1 block text-xs font-semibold">Payroll status</label><select name="status" class="w-full rounded-lg border px-3 py-2 text-sm"><option value="">All statuses</option>@foreach(['draft'=>'Draft','approved'=>'Approved','paid'=>'Paid'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="flex items-end gap-2"><button class="flex-1 rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Apply</button><a href="{{ route('admin.staff-payroll.report') }}" class="rounded-lg border px-3 py-2 text-sm">Reset</a></div>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach([
            ['Payroll records', number_format((int)$summary->records)],
            ['Basic salary', '৳'.number_format((float)$summary->basic_total, 2)],
            ['Allowances', '৳'.number_format((float)$summary->allowance_total, 2)],
            ['Deductions', '৳'.number_format((float)$summary->deduction_total, 2)],
            ['Net payroll', '৳'.number_format((float)$summary->net_total, 2)],
        ] as [$label,$value])
            <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-semibold text-slate-500">{{ $label }}</p><p class="mt-2 text-xl font-black text-slate-900">{{ $value }}</p></div>
        @endforeach
    </div>

    <div class="flex justify-end"><a href="{{ route('admin.staff-payroll.report.export', request()->query()) }}" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Export CSV (Excel compatible)</a></div>

    <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
        <h2 class="border-b px-5 py-4 font-bold">Month-by-month summary</h2>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Month</th><th class="px-5 py-3">Records</th><th class="px-5 py-3">Deductions</th><th class="px-5 py-3">Net payroll</th></tr></thead><tbody class="divide-y">
            @forelse($monthly as $month)<tr><td class="px-5 py-3 font-semibold">{{ \Carbon\Carbon::createFromFormat('Y-m', $month->payroll_month)->format('F Y') }}</td><td class="px-5 py-3">{{ $month->records }}</td><td class="px-5 py-3">৳{{ number_format((float)$month->deduction_total, 2) }}</td><td class="px-5 py-3 font-bold">৳{{ number_format((float)$month->net_total, 2) }}</td></tr>
            @empty<tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No payroll data for these filters.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
        <h2 class="border-b px-5 py-4 font-bold">Staff-wise payroll summary</h2>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Staff member</th><th class="px-5 py-3">Employee ID</th><th class="px-5 py-3">Payroll records</th><th class="px-5 py-3">Total net paid / due</th></tr></thead><tbody class="divide-y">
            @forelse($byStaff as $staff)<tr><td class="px-5 py-3 font-semibold">{{ $staff->name }}</td><td class="px-5 py-3">{{ $staff->employee_code ?: '—' }}</td><td class="px-5 py-3">{{ $staff->records }}</td><td class="px-5 py-3 font-bold">৳{{ number_format((float)$staff->net_total, 2) }}</td></tr>
            @empty<tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No staff payroll data.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
