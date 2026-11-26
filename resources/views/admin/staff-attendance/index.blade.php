@extends('layouts.admin')
@section('title', 'Staff Attendance')
@section('heading', 'Staff Attendance')
@section('content')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <div><label class="mb-1 block text-xs font-semibold">Attendance date</label><input type="date" name="date" value="{{ $date }}" required class="rounded-lg border px-3 py-2 text-sm"></div>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Load register</button>
        <a href="{{ route('admin.staff-payroll.index') }}" class="rounded-lg border px-4 py-2 text-sm font-semibold">Staff Payroll</a>
    </form>
    <form method="POST" action="{{ route('admin.staff-attendance.save') }}">@csrf
        <input type="hidden" name="attendance_date" value="{{ $date }}">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-3">Staff member</th><th class="px-3 py-3">Branch / Role</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Check in</th><th class="px-3 py-3">Check out</th><th class="px-3 py-3">Notes</th></tr></thead>
                <tbody class="divide-y">
                @forelse ($staff as $index => $member)
                    @php($profile = $member->staffProfile)
                    @php($record = $profile ? $records->get($profile->id) : null)
                    <tr>
                        <td class="px-3 py-3"><p class="font-semibold">{{ $member->name }}</p><p class="text-xs text-slate-500">{{ $profile?->employee_code ?? 'Employee ID not set' }}</p><input type="hidden" name="records[{{ $index }}][user_id]" value="{{ $member->id }}"></td>
                        <td class="px-3 py-3"><p>{{ $member->branch?->name ?? '—' }}</p><p class="text-xs text-slate-500">{{ $member->roleLabel() }}</p></td>
                        <td class="px-3 py-3"><select name="records[{{ $index }}][status]" required class="rounded-lg border px-2 py-2 text-xs">@foreach(['present'=>'Present','absent'=>'Absent','late'=>'Late','leave'=>'Leave','half_day'=>'Half day','remote'=>'Remote'] as $v=>$label)<option value="{{ $v }}" @selected(($record?->status ?? 'present')===$v)>{{ $label }}</option>@endforeach</select></td>
                        <td class="px-3 py-3"><input type="time" name="records[{{ $index }}][check_in]" value="{{ $record?->check_in ? substr($record->check_in,0,5) : '' }}" class="w-28 rounded border px-2 py-2 text-xs"></td>
                        <td class="px-3 py-3"><input type="time" name="records[{{ $index }}][check_out]" value="{{ $record?->check_out ? substr($record->check_out,0,5) : '' }}" class="w-28 rounded border px-2 py-2 text-xs"></td>
                        <td class="px-3 py-3"><input name="records[{{ $index }}][notes]" value="{{ $record?->notes }}" maxlength="500" class="w-36 rounded border px-2 py-2 text-xs" placeholder="Optional note"></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-10 text-center text-slate-500">No active teachers or branch staff found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($staff->isNotEmpty())<div class="mt-5 flex justify-end"><button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">Save attendance</button></div>@endif
    </form>
</section>
@endsection
