@extends('layouts.admin')
@section('title', 'Question Bank')
@section('heading', 'Question Bank')
@section('content')
<div class="grid gap-5 xl:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-slate-900">Add a reusable question</h2>
        <form method="POST" action="{{ route('admin.question-bank.store') }}" class="mt-4 space-y-3">
            @csrf
            <textarea name="question" rows="3" required placeholder="Write the question..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('question') }}</textarea>
            <div class="grid gap-3 sm:grid-cols-3">
                <select name="type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="mcq">MCQ</option><option value="short">Short answer</option><option value="cq">Creative (CQ)</option></select>
                <select name="difficulty" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="easy">Easy</option><option value="medium" selected>Medium</option><option value="hard">Hard</option></select>
                <input name="category" placeholder="Topic / category" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="grid gap-2 sm:grid-cols-2">@for($i=0;$i<4;$i++)<input name="options[]" placeholder="Option {{ chr(65+$i) }} (MCQ)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">@endfor</div>
            <div class="grid gap-3 sm:grid-cols-2">
                <select name="correct_option_index" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Correct option (A–D)</option>@for($i=0;$i<4;$i++)<option value="{{ $i }}">{{ chr(65+$i) }}</option>@endfor</select>
                <input type="number" name="marks" step="0.25" min="0.25" value="1" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <textarea name="explanation" rows="2" placeholder="Answer explanation (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            <button class="rounded-lg bg-orange-600 px-4 py-2 font-bold text-white">Save to Question Bank</button>
        </form>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-slate-900">Bulk import from CSV</h2>
        <p class="mt-2 text-sm text-slate-500">CSV columns: question,type,marks,options,correct_index,category,difficulty,explanation. Separate MCQ options with | and use a zero-based correct_index.</p>
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.question-bank.import') }}" class="mt-4 space-y-3">
            @csrf
            <input type="file" name="file" accept=".csv,.txt,text/csv" required class="w-full rounded-lg border border-slate-300 p-2 text-sm">
            <button class="rounded-lg bg-slate-900 px-4 py-2 font-bold text-white">Import CSV</button>
            <a class="ml-3 text-sm font-semibold text-orange-700" href="{{ route('admin.question-bank.template') }}">Download CSV template</a>
        </form>
    </section>
</div>



<form method="GET" class="my-4 flex flex-wrap gap-2">
    @if($exam)<input type="hidden" name="exam_id" value="{{ $exam->id }}">@endif
    <input name="q" value="{{ request('q') }}" placeholder="Search questions" class="min-w-48 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <input name="category" value="{{ request('category') }}" placeholder="Topic" class="w-36 rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <select name="difficulty" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All levels</option>@foreach(['easy','medium','hard'] as $level)<option value="{{ $level }}" @selected(request('difficulty')===$level)>{{ ucfirst($level) }}</option>@endforeach</select>
    <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Filter</button>
</form>
@if($exam)
<form id="attach-bank-items" method="POST" action="{{ route('admin.question-bank.attach', $exam) }}" class="mb-4 rounded-2xl border border-orange-200 bg-orange-50 p-4">
    @csrf
    <div class="flex flex-wrap items-center justify-between gap-3"><p class="font-bold text-slate-900">Add selected bank questions to: {{ $exam->title }}</p><button class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-bold text-white">Add selected to exam</button></div>
@endif
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
    <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr>@if($exam)<th class="px-4 py-3">Select</th>@endif<th class="px-4 py-3">Question</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Topic</th><th class="px-4 py-3">Level</th><th class="px-4 py-3">Marks</th></tr></thead><tbody class="divide-y divide-slate-100">
    @forelse($items as $item)<tr>
        @if($exam)<td class="px-4 py-3"><input type="checkbox" name="item_ids[]" value="{{ $item->id }}" form="attach-bank-items"></td>@endif
        <td class="max-w-xl px-4 py-3">{{ $item->question }}</td><td class="px-4 py-3 uppercase">{{ $item->type }}</td><td class="px-4 py-3">{{ $item->category ?: '—' }}</td><td class="px-4 py-3">{{ ucfirst($item->difficulty) }}</td><td class="px-4 py-3">{{ $item->marks }}</td>
    </tr>@empty<tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No bank questions found.</td></tr>@endforelse
    </tbody></table></div>
</div>
@if($exam)</form>@endif
<div class="mt-4">{{ $items->links() }}</div>
@endsection
