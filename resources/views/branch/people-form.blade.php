@extends('layouts.portal')
@section('title', $user->exists ? 'Edit profile' : 'Add '.$type)
@section('heading', $user->exists ? 'Edit '.$type.' — '.$user->name : 'Add '.$type.' — '.$branch->name)
@section('content')
<form method="POST" action="{{ $user->exists ? route('branch.people.update', $user) : route('branch.people.store', $type) }}" class="max-w-4xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    @if($user->exists) @method('PUT') @endif
    <div class="rounded-xl bg-slate-50 p-3 text-sm"><span class="font-semibold">Branch:</span> {{ $branch->name }} · <span class="font-semibold">Role:</span> {{ $type === 'student' ? 'Student' : 'Teacher / Instructor' }}<p class="mt-1 text-xs text-slate-500">Branch assignment is controlled by the system and cannot be changed here.</p></div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="mb-1 block text-sm font-semibold">Full name</label><input name="name" value="{{ old('name', $user->name) }}" required class="w-full rounded-lg border px-3 py-2.5 text-sm"></div>
        <div><label class="mb-1 block text-sm font-semibold">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full rounded-lg border px-3 py-2.5 text-sm"></div>
        <div><label class="mb-1 block text-sm font-semibold">Phone</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-lg border px-3 py-2.5 text-sm"></div>
        <div><label class="mb-1 block text-sm font-semibold">Designation</label><input name="designation" value="{{ old('designation', $user->designation) }}" class="w-full rounded-lg border px-3 py-2.5 text-sm"></div>
        <div class="sm:col-span-2"><label class="mb-1 block text-sm font-semibold">{{ $user->exists ? 'New password (leave blank to keep current)' : 'Temporary password' }}</label><input type="password" name="password" @required(! $user->exists) autocomplete="new-password" class="w-full rounded-lg border px-3 py-2.5 text-sm" minlength="8"></div>
    </div>

    @if($type === 'student')
        @php($profile = $user->studentProfile)
        <section class="rounded-xl border border-sky-200 bg-sky-50/50 p-4">
            <h3 class="mb-3 font-bold">Academic profile</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach(['student_code'=>'Student ID','school_name'=>'School / College','academic_class'=>'Class / Level','academic_roll'=>'Roll','academic_session'=>'Session'] as $field=>$label)
                    <div><label class="mb-1 block text-sm font-semibold">{{ $label }}</label><input name="{{ $field }}" value="{{ old($field, $profile?->{$field}) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
                @endforeach
                <div><label class="mb-1 block text-sm font-semibold">Date of birth</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth', $profile?->date_of_birth?->format('Y-m-d')) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
                <div><label class="mb-1 block text-sm font-semibold">Student status</label><select name="student_status" class="w-full rounded-lg border px-3 py-2 text-sm">@foreach(['active'=>'Active','inactive'=>'Inactive','suspended'=>'Suspended','graduated'=>'Graduated'] as $v=>$label)<option value="{{ $v }}" @selected(old('student_status', $profile?->status ?? 'active')===$v)>{{ $label }}</option>@endforeach</select></div>
            </div>
        </section>
    @else
        @php($profile = $user->staffProfile)
        <section class="rounded-xl border border-amber-200 bg-amber-50/50 p-4">
            <h3 class="mb-3 font-bold">Employment profile</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach(['employee_code'=>'Employee ID','department'=>'Department','staff_designation'=>'Job title','highest_qualification'=>'Highest qualification'] as $field=>$label)
                    <div><label class="mb-1 block text-sm font-semibold">{{ $label }}</label><input name="{{ $field }}" value="{{ old($field, $field === 'staff_designation' ? $profile?->designation : $profile?->{$field}) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
                @endforeach
                <div><label class="mb-1 block text-sm font-semibold">Base salary (BDT)</label><input type="number" min="0" step="0.01" name="base_salary" value="{{ old('base_salary', $profile?->base_salary ?? 0) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
                <div><label class="mb-1 block text-sm font-semibold">Employment type</label><select name="employment_type" class="w-full rounded-lg border px-3 py-2 text-sm"><option value="">Select</option>@foreach(['full_time'=>'Full time','part_time'=>'Part time','contract'=>'Contract','visiting'=>'Visiting'] as $v=>$label)<option value="{{ $v }}" @selected(old('employment_type', $profile?->employment_type)===$v)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="mb-1 block text-sm font-semibold">Joining date</label><input type="date" name="joining_date" value="{{ old('joining_date', $profile?->joining_date?->format('Y-m-d')) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
                <div><label class="mb-1 block text-sm font-semibold">Employment status</label><select name="employment_status" class="w-full rounded-lg border px-3 py-2 text-sm">@foreach(['active'=>'Active','on_leave'=>'On leave','suspended'=>'Suspended','resigned'=>'Resigned','terminated'=>'Terminated'] as $v=>$label)<option value="{{ $v }}" @selected(old('employment_status', $profile?->employment_status ?? 'active')===$v)>{{ $label }}</option>@endforeach</select></div>
            </div>
        </section>
    @endif

    @if($user->exists)
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> Login account active</label>
    @endif
    @if($errors->any())<div class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="flex gap-2"><button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">Save</button><a href="{{ $type === 'student' ? route('branch.students') : route('branch.staff') }}" class="rounded-lg border px-5 py-2.5 text-sm font-semibold">Cancel</a></div>
</form>
@endsection
