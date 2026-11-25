@extends('layouts.portal')
@section('title', 'My students')
@section('heading', 'My students')
@section('content')
<form method="GET" class="mb-4 flex max-w-md gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Search name, email or phone" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Search</button>
</form>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-5 py-3">Student</th>
                <th class="px-5 py-3">Contact</th>
                <th class="px-5 py-3">Courses</th>
                <th class="px-5 py-3">Avg. exam score</th>
                <th class="px-5 py-3">Course progress</th>
                <th class="px-5 py-3">Last activity</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($students as $student)
                <tr>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <img src="{{ $student->avatarUrl() }}" class="h-9 w-9 rounded-full" alt="">
                            <div>
                                <p class="font-semibold text-slate-800">{{ $student->name }}</p>
                                <p class="text-xs text-slate-500">{{ $student->designation ?: 'Student' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-slate-600">
                        <p>{{ $student->email }}</p>
                        <p class="text-xs text-slate-500">{{ $student->phone }}</p>
                    </td>
                    <td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ $student->enrollments_count }}</span></td>
                    <td class="px-5 py-3 font-semibold text-slate-700">{{ isset($scores[$student->id]) ? round($scores[$student->id], 1).'%' : '—' }}</td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-600" style="width: {{ $progress[$student->id] ?? 0 }}%"></div></div>
                            <span class="text-xs font-bold text-slate-700">{{ $progress[$student->id] ?? 0 }}%</span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-xs text-slate-500">{{ !empty($lastActivity[$student->id]) ? \Carbon\Carbon::parse($lastActivity[$student->id])->diffForHumans() : 'No activity' }}</td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('teacher.students.show', $student) }}" class="text-sm font-bold text-emerald-700">View progress →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500">No students found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $students->links() }}</div>
@endsection
