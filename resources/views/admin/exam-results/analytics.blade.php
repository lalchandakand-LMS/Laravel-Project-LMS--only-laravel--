@extends('layouts.admin')
@section('title', 'Exam Analytics')
@section('heading', 'Analytics · '.$exam->title)
@section('content')
<div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
@foreach([['Attempts',$total],['Unique students',$uniqueStudents],['Pass rate',$total ? round($passed/$total*100,1).'%' : '0%'],['Average score',number_format($average,2)]] as [$label,$value])
<div class="rounded-xl border border-slate-200 bg-white p-5"><p class="text-xs font-semibold uppercase text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-extrabold text-slate-900">{{ $value }}</p></div>
@endforeach
</div>
<div class="grid gap-5 xl:grid-cols-2">
<section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Score distribution</h2><div class="mt-4 space-y-3">@foreach($distribution as $range=>$count)<div><div class="mb-1 flex justify-between text-sm"><span>{{ $range }}</span><strong>{{ $count }}</strong></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-orange-500" style="width:{{ $total ? ($count/$total*100) : 0 }}%"></div></div></div>@endforeach</div></section>
<section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Pass / fail</h2><p class="mt-4 text-3xl font-extrabold text-emerald-700">{{ $passed }} passed</p><p class="mt-1 text-sm text-slate-500">{{ max(0,$total-$passed) }} below pass marks</p><a href="{{ route('admin.exams.results.index',$exam) }}" class="mt-4 inline-block font-semibold text-orange-700">Open student results →</a></section>
</div>
<section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="border-b p-4"><h2 class="font-bold">Question performance</h2></div><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Question</th><th class="px-4 py-3">Responses</th><th class="px-4 py-3">Correct</th><th class="px-4 py-3">Average marks</th></tr></thead><tbody class="divide-y">@foreach($questionStats as $stat)<tr><td class="max-w-xl px-4 py-3">{{ $stat['question']->question }}</td><td class="px-4 py-3">{{ $stat['responses'] }}</td><td class="px-4 py-3">{{ $stat['correct'] }}</td><td class="px-4 py-3">{{ $stat['average_marks'] }}</td></tr>@endforeach</tbody></table></div></section>
@endsection
