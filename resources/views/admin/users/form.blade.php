@extends('layouts.admin')
@section('title', $user->exists ? 'Edit user' : 'New user')
@section('heading', $user->exists ? 'Edit user · '.$user->name : 'Add a user')
@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}"
      class="max-w-4xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($user->exists) @method('PUT') @endif

    <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <img src="{{ $user->avatarUrl() }}" alt="Profile photo" class="h-16 w-16 rounded-full object-cover">
        <div class="min-w-0 flex-1">
            <label class="mb-1 block text-sm font-semibold text-slate-700">Profile photo upload</label>
            <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" class="w-full text-xs">
            <label class="mt-2 block text-xs font-semibold text-slate-600">Or paste a photo URL</label>
            <input type="url" name="avatar_url" value="{{ old('avatar_url', str_starts_with((string) $user->avatar, 'http') ? $user->avatar : '') }}" placeholder="https://example.com/profile.jpg" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <a href="{{ route('admin.media.index') }}" class="mt-1 inline-block text-xs font-semibold text-emerald-700">Open Media Library →</a>
        </div>
    </div>

    <section class="space-y-4">
        <h3 class="border-b pb-2 font-bold text-slate-900">Account & access</h3>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="mb-1 block text-sm font-semibold">Name</label><input name="name" value="{{ old('name', $user->name) }}" required class="w-full rounded-lg border px-3 py-2.5 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full rounded-lg border px-3 py-2.5 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Phone</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-lg border px-3 py-2.5 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Role</label><select id="user-role" name="role" class="w-full rounded-lg border px-3 py-2.5 text-sm">@foreach ($roles as $value => $label)<option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm font-semibold">Branch</label><select name="branch_id" class="w-full rounded-lg border px-3 py-2.5 text-sm"><option value="">— none —</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id', $user->branch_id) == $branch->id)>{{ $branch->name }} ({{ $branch->code }})</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm font-semibold">Designation (account)</label><input name="designation" value="{{ old('designation', $user->designation) }}" class="w-full rounded-lg border px-3 py-2.5 text-sm"></div>
            <div class="sm:col-span-2"><label class="mb-1 block text-sm font-semibold">{{ $user->exists ? 'New password (leave blank to keep current)' : 'Password' }}</label><input type="password" name="password" @required(! $user->exists) autocomplete="new-password" class="w-full rounded-lg border px-3 py-2.5 text-sm" placeholder="Minimum 8 characters"></div>
            <div class="sm:col-span-2"><label class="mb-1 block text-sm font-semibold">Bio</label><textarea name="bio" rows="2" class="w-full rounded-lg border px-3 py-2 text-sm">{{ old('bio', $user->bio) }}</textarea></div>
        </div>
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> Login account active</label>
    </section>

    <section id="student-profile" class="space-y-4 rounded-xl border border-sky-200 bg-sky-50/50 p-4">
        <div><h3 class="font-bold text-slate-900">Student academic profile</h3><p class="text-xs text-slate-500">These fields are saved when Role is Student.</p></div>
        @php($sp = $user->studentProfile)
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach (['student_code'=>'Student ID','school_name'=>'School / College','academic_class'=>'Class / Level','academic_roll'=>'Roll','academic_session'=>'Academic session','academic_group'=>'Group / Department','emergency_contact_name'=>'Emergency contact name','emergency_contact_phone'=>'Emergency contact phone','guardian_name'=>'Guardian name','guardian_phone'=>'Guardian phone','guardian_email'=>'Guardian email','quota_type'=>'Quota / special category'] as $field => $label)
                <div><label class="mb-1 block text-sm font-semibold">{{ $label }}</label><input name="{{ $field }}" value="{{ old($field, $sp?->{$field}) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
            @endforeach
            <div><label class="mb-1 block text-sm font-semibold">Date of birth</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth', $sp?->date_of_birth?->format('Y-m-d')) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Gender</label><select name="gender" class="w-full rounded-lg border px-3 py-2 text-sm"><option value="">Select</option>@foreach (['male'=>'Male','female'=>'Female','other'=>'Other'] as $v=>$label)<option value="{{ $v }}" @selected(old('gender', $sp?->gender)===$v)>{{ $label }}</option>@endforeach</select></div>
            <div class="sm:col-span-2"><label class="mb-1 block text-sm font-semibold">Quota notes</label><textarea name="quota_notes" rows="2" class="w-full rounded-lg border px-3 py-2 text-sm">{{ old('quota_notes', $sp?->quota_notes) }}</textarea></div>
            <div><label class="mb-1 block text-sm font-semibold">Student status</label><select name="student_status" class="w-full rounded-lg border px-3 py-2 text-sm">@foreach (['active'=>'Active','inactive'=>'Inactive','suspended'=>'Suspended','graduated'=>'Graduated'] as $v=>$label)<option value="{{ $v }}" @selected(old('student_status', $sp?->status ?? 'active')===$v)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm font-semibold">Present address</label><input name="present_address" value="{{ old('present_address', $sp?->present_address) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Permanent address</label><input name="permanent_address" value="{{ old('permanent_address', $sp?->permanent_address) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
        </div>
    </section>

    <section id="staff-profile" class="space-y-4 rounded-xl border border-amber-200 bg-amber-50/50 p-4">
        <div><h3 class="font-bold text-slate-900">Staff / Teacher employment profile</h3><p class="text-xs text-slate-500">These fields are saved for Teacher and Branch Manager roles.</p></div>
        @php($ep = $user->staffProfile)
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach (['employee_code'=>'Employee ID','department'=>'Department','staff_designation'=>'Job title / Designation','highest_qualification'=>'Highest qualification','institution'=>'Institution'] as $field => $label)
                <div><label class="mb-1 block text-sm font-semibold">{{ $label }}</label><input name="{{ $field }}" value="{{ old($field, $field === 'staff_designation' ? $ep?->designation : $ep?->{$field === 'staff_designation' ? 'designation' : $field}) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
            @endforeach
            <div><label class="mb-1 block text-sm font-semibold">Base salary (BDT)</label><input type="number" min="0" step="0.01" name="base_salary" value="{{ old('base_salary', $ep?->base_salary ?? 0) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Employment type</label><select name="employment_type" class="w-full rounded-lg border px-3 py-2 text-sm"><option value="">Select</option>@foreach (['full_time'=>'Full time','part_time'=>'Part time','contract'=>'Contract','visiting'=>'Visiting'] as $v=>$label)<option value="{{ $v }}" @selected(old('employment_type', $ep?->employment_type)===$v)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm font-semibold">Joining date</label><input type="date" name="joining_date" value="{{ old('joining_date', $ep?->joining_date?->format('Y-m-d')) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Confirmation date</label><input type="date" name="confirmation_date" value="{{ old('confirmation_date', $ep?->confirmation_date?->format('Y-m-d')) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Experience (years)</label><input type="number" min="0" max="60" name="experience_years" value="{{ old('experience_years', $ep?->experience_years) }}" class="w-full rounded-lg border px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Employment status</label><select name="employment_status" class="w-full rounded-lg border px-3 py-2 text-sm">@foreach (['active'=>'Active','on_leave'=>'On leave','suspended'=>'Suspended','resigned'=>'Resigned','terminated'=>'Terminated'] as $v=>$label)<option value="{{ $v }}" @selected(old('employment_status', $ep?->employment_status ?? 'active')===$v)>{{ $label }}</option>@endforeach</select></div>
            <div class="sm:col-span-2"><label class="mb-1 block text-sm font-semibold">Professional bio</label><textarea name="professional_bio" rows="3" class="w-full rounded-lg border px-3 py-2 text-sm">{{ old('professional_bio', $ep?->professional_bio) }}</textarea></div>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-slate-50 p-3">
        <p class="mb-2 text-sm font-bold text-slate-700">Link guardian to students</p>
        <p class="mb-2 text-xs text-slate-500">Only used for Guardian / Parent accounts.</p>
        <select name="children[]" multiple size="5" class="w-full rounded-lg border px-3 py-2 text-sm">
            @foreach ($students as $student)<option value="{{ $student->id }}" @selected(in_array($student->id, old('children', $user->exists ? $user->children->pluck('id')->all() : [])))>{{ $student->name }} — {{ $student->email }}</option>@endforeach
        </select>
    </section>

    @if ($errors->any())<div class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700"><p class="font-bold">Please correct the following:</p><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="flex gap-2"><button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">Save profile</button><a href="{{ route('admin.users.index') }}" class="rounded-lg border px-5 py-2.5 text-sm font-semibold">Cancel</a></div>
</form>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const role = document.getElementById('user-role');
    const student = document.getElementById('student-profile');
    const staff = document.getElementById('staff-profile');
    const update = () => {
        student.hidden = role.value !== 'student';
        staff.hidden = !['instructor', 'branch_manager'].includes(role.value);
        student.querySelectorAll('input, select, textarea').forEach((field) => field.disabled = student.hidden);
        staff.querySelectorAll('input, select, textarea').forEach((field) => field.disabled = staff.hidden);
    };
    role.addEventListener('change', update);
    update();
});
</script>
@endsection
