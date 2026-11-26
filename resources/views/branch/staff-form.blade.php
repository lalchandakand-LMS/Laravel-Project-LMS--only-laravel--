@extends('layouts.portal')
@section('title', $staff->exists ? 'Edit teacher' : 'Add teacher')
@section('heading', ($staff->exists ? 'Edit teacher — ' : 'Add teacher — ').$branch->name)
@section('content')
<form method="POST" action="{{ $staff->exists ? route('branch.staff.update',$staff) : route('branch.staff.store') }}" class="max-w-3xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    @if($staff->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-sm font-semibold text-slate-700">Full name<input name="name" required value="{{ old('name',$staff->name) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
        <label class="text-sm font-semibold text-slate-700">Email<input type="email" name="email" required value="{{ old('email',$staff->email) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
        <label class="text-sm font-semibold text-slate-700">Phone<input name="phone" value="{{ old('phone',$staff->phone) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
        <label class="text-sm font-semibold text-slate-700">Designation<input name="designation" value="{{ old('designation',$staff->designation) }}" placeholder="English Teacher" class="mt-1 w-full rounded-lg border-slate-300"></label>
        <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Short bio<textarea name="bio" rows="3" class="mt-1 w-full rounded-lg border-slate-300">{{ old('bio',$staff->bio) }}</textarea></label>
        <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Password {{ $staff->exists ? '(leave blank to keep current)' : '' }}<input type="password" name="password" {{ $staff->exists ? '' : 'required' }} minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-lg border-slate-300"></label>
    </div>
    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$staff->exists ? $staff->is_active : true))> Active teacher account</label>
    <p class="text-xs text-slate-500">Teacher role and branch assignment are locked to {{ $branch->name }}. Only Admin can transfer this staff member to another branch.</p>
    <div class="flex gap-2"><button class="rounded-lg bg-orange-600 px-5 py-2.5 text-sm font-bold text-white">Save teacher</button><a href="{{ route('branch.staff') }}" class="rounded-lg border px-5 py-2.5 text-sm font-semibold">Cancel</a></div>
</form>
@endsection
