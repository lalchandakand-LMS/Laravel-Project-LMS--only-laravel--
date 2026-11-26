@extends('layouts.admin')
@section('title', 'Certificates')
@section('heading', 'Certificates')
@section('content')
<div class="mb-5 rounded-2xl border border-blue-100 bg-blue-50/70 p-5">
    <div class="mb-3"><h2 class="font-extrabold text-slate-900">Generate Certificate</h2><p class="mt-1 text-xs text-slate-500">Only students with an active eligible enrolment and 100% course completion can receive a certificate.</p></div>
    <form method="POST" action="{{ route('admin.certificates.store') }}" class="grid gap-3 md:grid-cols-[1fr_1fr_auto]">@csrf
        <select name="user_id" required class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"><option value="">Select student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->name }} — {{ $student->email }}</option>@endforeach</select>
        <select name="course_id" required class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"><option value="">Select completed course</option>@foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->title }}</option>@endforeach</select>
        <button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white">Generate Certificate</button>
    </form>
</div>
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-500">Issued learner certificates and verification codes.</p>
    <form method="GET" action="{{ route('admin.certificates.index') }}" class="flex gap-2">
        <input name="q" value="{{ $search }}" placeholder="Search code, student or course" class="w-64 rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Search</button>
    </form>
</div>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Certificate</th><th class="px-5 py-3">Student</th><th class="px-5 py-3">Course</th><th class="px-5 py-3">Issued</th><th class="px-5 py-3">Verification</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($certificates as $certificate)
            <tr><td class="px-5 py-4 font-bold text-slate-800">{{ $certificate->code }}</td><td class="px-5 py-4">{{ $certificate->user?->name ?? 'Deleted user' }}</td><td class="px-5 py-4">{{ $certificate->course?->title ?? 'Deleted course' }}</td><td class="px-5 py-4 text-slate-500">{{ $certificate->issued_at?->format('d M Y') }}</td><td class="px-5 py-4"><a class="font-semibold text-blue-700 hover:underline" target="_blank" rel="noopener" href="{{ route('certificates.verify', $certificate->code) }}">Verify certificate</a></td></tr>
        @empty
            <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No certificates found.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="border-t border-slate-100 px-5 py-4">{{ $certificates->links() }}</div>
</div>
@endsection
