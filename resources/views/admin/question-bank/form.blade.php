@extends('layouts.admin')
@section('title', 'Edit bank question')
@section('heading', 'Edit Question Bank Item')
@section('content')
<form method="POST" action="{{ route('admin.question-bank.update', $item) }}" class="max-w-3xl space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf @method('PUT')
    <div><label class="mb-1 block text-sm font-semibold">Question</label><textarea name="question" rows="4" required class="w-full rounded-lg border border-slate-300 p-3">{{ old('question',$item->question) }}</textarea></div>
    <div class="grid gap-3 sm:grid-cols-3">
        <select name="type" class="rounded-lg border border-slate-300 p-2"><option value="mcq" @selected(old('type',$item->type)==='mcq')>MCQ</option><option value="short" @selected(old('type',$item->type)==='short')>Short</option><option value="cq" @selected(old('type',$item->type)==='cq')>CQ</option></select>
        <select name="difficulty" class="rounded-lg border border-slate-300 p-2">@foreach(['easy','medium','hard'] as $level)<option value="{{ $level }}" @selected(old('difficulty',$item->difficulty)===$level)>{{ ucfirst($level) }}</option>@endforeach</select>
        <input name="category" value="{{ old('category',$item->category) }}" placeholder="Topic" class="rounded-lg border border-slate-300 p-2">
    </div>
    @php($options = old('options',$item->options ?? ['','','','']))
    @php($correct = old('correct_option_index',$item->correct_option_index))
    <div class="space-y-2">@foreach($options as $i=>$option)<div class="flex items-center gap-2"><input type="radio" name="correct_option_index" value="{{ $i }}" @checked((string)$correct===(string)$i)><input name="options[{{ $i }}]" value="{{ $option }}" placeholder="Option {{ $i+1 }}" class="w-full rounded-lg border border-slate-300 p-2"></div>@endforeach</div>
    <div class="grid gap-3 sm:grid-cols-2"><input type="number" step="0.25" min="0.25" name="marks" value="{{ old('marks',$item->marks) }}" class="rounded-lg border border-slate-300 p-2"><textarea name="explanation" rows="2" placeholder="Explanation" class="rounded-lg border border-slate-300 p-2">{{ old('explanation',$item->explanation) }}</textarea></div>
    <div class="flex gap-2"><button class="rounded-lg bg-orange-600 px-5 py-2 font-bold text-white">Update question</button><a href="{{ route('admin.question-bank.index') }}" class="rounded-lg border px-5 py-2">Cancel</a></div>
</form>
@endsection
