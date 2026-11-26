@extends('layouts.admin')
@section('title', $coupon->exists ? 'Edit coupon' : 'New coupon')
@section('heading', $coupon->exists ? 'Edit coupon' : 'New coupon')
@section('content')
<form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" class="space-y-6">
    @csrf
    @if ($coupon->exists) @method('PUT') @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 font-bold text-slate-900">Basic discount</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-semibold text-slate-700">Coupon code *</label>
                <input name="code" value="{{ old('code', $coupon->code) }}" required maxlength="40" placeholder="EID2026" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm uppercase">
                <p class="mt-1 text-xs text-slate-500">Letters, numbers, hyphen and underscore only. Saved in uppercase.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Discount type</label>
                <select name="type" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="percent" @selected(old('type', $coupon->type) === 'percent')>Percentage</option>
                    <option value="fixed" @selected(old('type', $coupon->type) === 'fixed')>Fixed amount (৳)</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Discount value *</label>
                <input type="number" min="0.01" step="0.01" name="value" value="{{ old('value', $coupon->value ?? 0) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Minimum eligible amount (৳)</label>
                <input type="number" min="0" step="0.01" name="min_amount" value="{{ old('min_amount', $coupon->min_amount ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Maximum discount (৳)</label>
                <input type="number" min="0.01" step="0.01" name="max_discount" value="{{ old('max_discount', $coupon->max_discount) }}" placeholder="Leave blank for no cap" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-1 font-bold text-slate-900">Availability & limits</h2>
        <p class="mb-4 text-xs text-slate-500">A zero usage limit means unlimited. Pending orders reserve a coupon use until the order is cancelled or failed.</p>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Global usage limit</label>
                <input type="number" min="0" name="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Usage per student</label>
                <input type="number" min="0" name="per_user_limit" value="{{ old('per_user_limit', $coupon->per_user_limit ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Branch restriction</label>
                <select name="branch_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">All branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id', $coupon->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Starts at</label>
                <input type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($coupon->starts_at)->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Expires at</label>
                <input type="datetime-local" name="expires_at" value="{{ old('expires_at', optional($coupon->expires_at)->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div class="flex flex-col justify-end gap-3">
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="checkbox" name="first_order_only" value="1" @checked(old('first_order_only', $coupon->first_order_only ?? false)) class="rounded text-orange-600"> First order only
                </label>
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->is_active ?? true)) class="rounded text-orange-600"> Active
                </label>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-bold text-slate-900">Product targeting</h2>
        <p class="mb-4 mt-1 text-xs text-slate-500">Leave all selections empty to apply the coupon to the entire cart. If selected, only matching items receive the discount.</p>
        <div class="grid gap-6 lg:grid-cols-3">
            <div>
                <h3 class="mb-2 text-sm font-semibold text-slate-800">Courses</h3>
                <div class="max-h-64 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-3">
                    @foreach ($courses as $course)
                        <label class="flex items-start gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="course_ids[]" value="{{ $course->id }}" @checked(in_array($course->id, old('course_ids', $coupon->course_ids ?? []))) class="mt-1 rounded">
                            <span>{{ $course->title }}</span>
                        </label>
                    @endforeach
                    @if ($courses->isEmpty()) <p class="text-xs text-slate-500">No published courses.</p> @endif
                </div>
            </div>
            <div>
                <h3 class="mb-2 text-sm font-semibold text-slate-800">Categories</h3>
                <div class="max-h-64 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-3">
                    @foreach ($categories as $category)
                        <label class="flex items-start gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', $coupon->category_ids ?? []))) class="mt-1 rounded">
                            <span>{{ $category->name }}</span>
                        </label>
                    @endforeach
                    @if ($categories->isEmpty()) <p class="text-xs text-slate-500">No active categories.</p> @endif
                </div>
            </div>
            <div>
                <h3 class="mb-2 text-sm font-semibold text-slate-800">E-books</h3>
                <div class="max-h-64 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-3">
                    @foreach ($ebooks as $ebook)
                        <label class="flex items-start gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="ebook_ids[]" value="{{ $ebook->id }}" @checked(in_array($ebook->id, old('ebook_ids', $coupon->ebook_ids ?? []))) class="mt-1 rounded">
                            <span>{{ $ebook->title }}</span>
                        </label>
                    @endforeach
                    @if ($ebooks->isEmpty()) <p class="text-xs text-slate-500">No published e-books.</p> @endif
                </div>
            </div>
        </div>
    </section>

    <div class="flex flex-wrap justify-end gap-3">
        <a href="{{ route('admin.coupons.index') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
        <button class="rounded-lg bg-orange-600 px-5 py-2.5 text-sm font-bold text-white">Save coupon</button>
    </div>
</form>
@endsection
