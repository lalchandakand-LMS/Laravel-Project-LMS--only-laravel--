@extends('layouts.admin')
@section('title', $flashSale->exists ? 'Edit flash sale' : 'Create flash sale')
@section('heading', $flashSale->exists ? 'Edit: '.$flashSale->name : 'Create Flash Sale')
@section('content')
<form method="POST" action="{{ $flashSale->exists ? route('admin.flash-sales.update', $flashSale) : route('admin.flash-sales.store') }}" class="max-w-5xl space-y-6">
    @csrf
    @if($flashSale->exists) @method('PUT') @endif
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-bold text-slate-900">Campaign details</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="text-sm font-semibold text-slate-700 md:col-span-2">Campaign name *
                <input name="name" required maxlength="160" value="{{ old('name', $flashSale->name) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            <label class="text-sm font-semibold text-slate-700">Discount type *
                <select name="type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                    <option value="percent" @selected(old('type', $flashSale->type) === 'percent')>Percentage (%)</option>
                    <option value="fixed" @selected(old('type', $flashSale->type) === 'fixed')>Fixed amount (৳)</option>
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">Discount value *
                <input type="number" name="value" min="0.01" max="10000000" step="0.01" required value="{{ old('value', $flashSale->value) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                <span class="mt-1 block text-xs font-normal text-slate-500">Percentage discounts cannot exceed 100%.</span>
            </label>
            <label class="text-sm font-semibold text-slate-700">Starts at *
                <input type="datetime-local" name="starts_at" required value="{{ old('starts_at', optional($flashSale->starts_at)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            <label class="text-sm font-semibold text-slate-700">Ends at *
                <input type="datetime-local" name="ends_at" required value="{{ old('ends_at', optional($flashSale->ends_at)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            <label class="text-sm font-semibold text-slate-700 md:col-span-2">Description
                <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">{{ old('description', $flashSale->description) }}</textarea>
            </label>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 md:col-span-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $flashSale->is_active))> Enable campaign (schedule still applies)</label>
        </div>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-bold text-slate-900">Target courses and categories</h2>
        <p class="mt-1 text-sm text-slate-500">A sale can target multiple courses, multiple categories, or both. At least one target is required. Categories match a course's primary or additional assigned category.</p>
        <div class="mt-5 grid gap-6 md:grid-cols-2">
            <div>
                <h3 class="mb-2 text-sm font-bold text-slate-800">Courses</h3>
                <div class="max-h-72 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-3">
                    @foreach($courses as $course)
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="course_ids[]" value="{{ $course->id }}" @checked(in_array($course->id, old('course_ids', $selectedCourseIds)))><span>{{ $course->title }}</span></label>
                    @endforeach
                </div>
            </div>
            <div>
                <h3 class="mb-2 text-sm font-bold text-slate-800">Categories</h3>
                <div class="max-h-72 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-3">
                    @foreach($categories as $category)
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', $selectedCategoryIds)))><span>{{ $category->name }}</span></label>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    <div class="flex flex-wrap gap-3"><button class="rounded-lg bg-orange-600 px-6 py-3 text-sm font-bold text-white">Save flash sale</button><a href="{{ route('admin.flash-sales.index') }}" class="rounded-lg border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700">Cancel</a></div>
</form>
@endsection
