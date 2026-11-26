@extends('layouts.admin')
@section('title', 'Certificates')
@section('heading', 'Certificates')
@section('content')
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
