@extends('layouts.admin')
@section('title', $branch->exists ? 'Edit branch' : 'New branch')
@section('heading', $branch->exists ? 'Edit branch · '.$branch->name : 'New branch')
@section('content')
<form method="POST" action="{{ $branch->exists ? route('admin.branches.update', $branch) : route('admin.branches.store') }}"
      class="max-w-3xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($branch->exists) @method('PUT') @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Branch name</label>
            <input name="name" value="{{ old('name', $branch->name) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Short code</label>
            <input name="code" value="{{ old('code', $branch->code) }}" required placeholder="e.g. DHK-01" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm uppercase">
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Phone</label>
            <input name="phone" value="{{ old('phone', $branch->phone) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Email</label>
            <input type="email" name="email" value="{{ old('email', $branch->email) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1 block text-sm font-semibold text-slate-700">Address</label>
            <textarea name="address" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('address', $branch->address) }}</textarea>
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1 block text-sm font-semibold text-slate-700">Branch manager</label>
            <select name="manager_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">— none —</option>
                @foreach ($managers as $manager)
                    <option value="{{ $manager->id }}" @selected(old('manager_id', $branch->manager_id) == $manager->id)>{{ $manager->name }} ({{ $manager->email }})</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Create the manager account first with the role “Branch manager”.</p>
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch->exists ? $branch->is_active : true)) class="rounded text-emerald-700"> Branch is open
    </label>

    <section class="rounded-xl border border-slate-200 p-4">
        <h2 class="font-bold text-slate-900">Payment methods available at this branch</h2>
        <p class="mt-1 text-xs text-slate-500">Students will only see the methods selected here. Cash and office collection are reserved for staff-side recording.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach ($paymentMethods as $key => $label)
                <label class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="payment_methods[]" value="{{ $key }}"
                        @checked(in_array($key, old('payment_methods', $branch->payment_methods ?? []), true))
                        class="rounded border-slate-300 text-emerald-700">
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 p-4">
        <h2 class="font-bold text-slate-900">Branch payment account details</h2>
        <p class="mt-1 text-xs text-slate-500">These details are shown only when the matching method is enabled for this branch.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach (['bkash_number' => 'bKash merchant/personal number', 'nagad_number' => 'Nagad number', 'rocket_number' => 'Rocket number', 'bank_name' => 'Bank name', 'bank_branch' => 'Bank branch', 'bank_account_name' => 'Bank account name', 'bank_account_number' => 'Bank account number'] as $field => $label)
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">{{ $label }}</label>
                    <input name="{{ $field }}" value="{{ old($field, $branch->{$field}) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            @endforeach
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-semibold text-slate-600">Payment instructions</label>
                <textarea name="payment_instructions" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Explain how students should pay and what reference to enter.">{{ old('payment_instructions', $branch->payment_instructions) }}</textarea>
            </div>
        </div>
    </section>

    <div class="flex gap-2">
        <button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">Save branch</button>
        <a href="{{ route('admin.branches.index') }}" class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600">Cancel</a>
    </div>
</form>
@endsection
