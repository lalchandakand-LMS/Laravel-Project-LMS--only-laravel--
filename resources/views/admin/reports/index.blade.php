@extends('layouts.admin')
@section('title', 'Reports')
@section('heading', 'Reports')
@section('content')
<div class="mb-6"><h2 class="text-xl font-black text-slate-900">Academy Reports Center</h2><p class="mt-1 text-sm text-slate-500">A single starting point for learner, teaching, attendance, exam and finance reporting.</p></div>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([['Students',$summary['students'],'bg-blue-50 text-blue-700'],['Teachers',$summary['teachers'],'bg-emerald-50 text-emerald-700'],['Courses',$summary['courses'],'bg-violet-50 text-violet-700'],['Batches',$summary['batches'],'bg-orange-50 text-orange-700'],['Enrollments',$summary['enrollments'],'bg-cyan-50 text-cyan-700'],['Certificates',$summary['certificates'],'bg-amber-50 text-amber-700'],['Exam attempts',$summary['exam_attempts'],'bg-fuchsia-50 text-fuchsia-700'],['Average exam score',$summary['average_score'],'bg-indigo-50 text-indigo-700']] as [$label,$value,$style])
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-black text-slate-900">{{ is_numeric($value) ? number_format($value, 1) : $value }}</p><span class="mt-2 inline-flex rounded-full px-2 py-1 text-[10px] font-bold {{ $style }}">Current total</span></div>
    @endforeach
</div>
<div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="font-extrabold text-slate-900">Detailed reports</h3><p class="mt-1 text-xs text-slate-500">Open the relevant module for filters, detailed records and exports where available.</p></div><div class="rounded-xl bg-emerald-50 px-4 py-3"><p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Paid revenue</p><p class="text-xl font-black text-emerald-800">৳{{ number_format($summary['paid_revenue'], 2) }}</p></div></div>
    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach([
            ['Student & Teacher Reports','Account rosters, profiles and branch filters','admin.users.index','♟'],
            ['Attendance Reports','Attendance sessions, review and branch records','admin.attendance.index','◷'],
            ['Payment & Revenue Reports','Verified payments, refunds and CSV exports','admin.payment-reports.index','৳'],
            ['Exam Performance','Exam lists, results and performance analytics','admin.exams.index','✎'],
            ['Certificate Registry','Issued certificates and eligibility-checked issuance','admin.certificates.index','✧'],
            ['Batch Reports','Batch rosters, schedules and enrolments','admin.batches.index','▤'],
            ['Activity Audit','Recent admin and system activity records','admin.activity-logs.index','⌁'],
            ['Course Reports','Course catalogue and enrolment details','admin.courses.index','▣'],
        ] as [$title,$description,$routeName,$icon])
            <a href="{{ route($routeName) }}" class="group rounded-xl border border-slate-200 p-4 transition hover:border-blue-300 hover:bg-blue-50/50"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-slate-100 text-lg text-slate-700 group-hover:bg-white">{{ $icon }}</span><div><h4 class="text-sm font-extrabold text-slate-800">{{ $title }}</h4><p class="mt-1 text-[11px] leading-5 text-slate-500">{{ $description }}</p></div></div></a>
        @endforeach
    </div>
</div>
@endsection
