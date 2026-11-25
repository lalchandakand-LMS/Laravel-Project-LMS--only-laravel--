@extends('layouts.admin')

@section('title', 'Recycle Bin')
@section('heading', 'Recycle Bin')

@section('content')
<div class="space-y-5">
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-sm text-slate-500">Items in bin</p><p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $total }}</p></div>
        @foreach (['users' => 'Accounts', 'courses' => 'Courses', 'lessons' => 'Lessons'] as $key => $label)
            <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $counts[$key] ?? 0 }}</p></div>
        @endforeach
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h2 class="font-bold text-slate-900">Deleted records</h2><p class="mt-1 text-sm text-slate-500">Restore deleted items or permanently remove records that have no protected dependencies.</p></div>
            <form method="GET" class="flex items-center gap-2">
                <label for="type" class="text-sm font-medium text-slate-600">Type</label>
                <select id="type" name="type" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All types</option>
                    @foreach ($counts as $key => $count)
                        <option value="{{ $key }}" @selected($type === $key)>{{ str($key)->replace('-', ' ')->title() }} ({{ $count }})</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($items->isNotEmpty())
            <form method="POST" action="{{ route('admin.recycle-bin.bulk-restore', ['type' => $type ?: 'users']) }}" id="bulk-restore-form" class="mt-4">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="mb-3 flex items-center gap-3">
                    <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800" @disabled(!$type)>Restore selected</button>
                    @if (!$type)<span class="text-xs text-slate-500">Choose a specific type to enable bulk restore.</span>@endif
                </div>
            </form>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500"><tr><th class="p-3">Select</th><th class="p-3">Record</th><th class="p-3">Type</th><th class="p-3">Deleted at</th><th class="p-3">Deleted by</th><th class="p-3 text-right">Actions</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach ($items as $item)
                            @php
                                $record = $item['record'];
                                $display = $record->name ?? $record->title ?? $record->label ?? $record->email ?? ('#'.$record->getKey());
                            @endphp
                            <tr>
                                <td class="p-3"><input type="checkbox" name="ids[]" value="{{ $record->getKey() }}" form="bulk-restore-form" class="rounded border-slate-300" @disabled(!$type || $item['type'] !== $type)></td>
                                <td class="p-3"><span class="font-semibold text-slate-900">{{ $display }}</span><span class="block text-xs text-slate-500">ID: {{ $record->getKey() }}</span></td>
                                <td class="p-3 capitalize text-slate-600">{{ str($item['type'])->replace('-', ' ') }}</td>
                                <td class="p-3 text-slate-600">{{ $item['deleted_at']?->format('d M Y, h:i A') }}</td>
                                <td class="p-3 text-slate-600">{{ $actors[$item['deleted_by']] ?? 'System / unknown' }}</td>
                                <td class="p-3"><div class="flex justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.recycle-bin.restore', [$item['type'], $record->getKey()]) }}">@csrf<button class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100">Restore</button></form>
                                    <form method="POST" action="{{ route('admin.recycle-bin.destroy', [$item['type'], $record->getKey()]) }}" onsubmit="return confirm('Permanently delete this record? This cannot be undone.');">@csrf @method('DELETE')<button class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100">Delete forever</button></form>
                                </div></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
        @else
            <div class="py-14 text-center"><div class="text-4xl">🗑️</div><h3 class="mt-3 font-bold text-slate-900">Recycle Bin is empty</h3><p class="mt-1 text-sm text-slate-500">Deleted records will appear here and can be restored.</p></div>
        @endif
    </section>
    <p class="text-xs text-slate-500">Safety lock: permanent deletion is blocked while a record is linked to student accounts, learning progress, exams, enrolments or transactions. Financial orders and payments are never included in this Recycle Bin. Records older than the configured retention period (30 days by default) are automatically purged only when no protected records depend on them; linked records are retained.</p>
</div>
@endsection
