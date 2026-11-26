@extends('layouts.admin')
@section('title', 'SEO Redirect Manager')
@section('heading', 'SEO Redirect Manager')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-3xl text-sm text-slate-600">Redirect old public URLs to current internal pages. Only 404 GET/HEAD requests are redirected; admin and learner workspaces are excluded.</p>
        <a href="{{ route('admin.seo.redirects.create') }}" class="rounded-xl bg-orange-600 px-4 py-2.5 text-sm font-bold text-white">+ Add Redirect</a>
    </div>
    <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_220px_auto]">
        <input name="q" value="{{ request('q') }}" placeholder="Search source or destination path" class="rounded-lg border-slate-300 text-sm">
        <select name="status" class="rounded-lg border-slate-300 text-sm"><option value="">All statuses</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Filter</button>
    </form>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3">Old URL path</th><th class="p-3">Destination</th><th class="p-3">Type</th><th class="p-3">Hits</th><th class="p-3">Status</th><th class="p-3">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($redirects as $redirect)
                        <tr class="align-top">
                            <td class="p-3 font-semibold text-slate-800">{{ $redirect->source_path }}</td>
                            <td class="max-w-sm break-all p-3 text-slate-600">{{ $redirect->destination_path }}</td>
                            <td class="p-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold">{{ $redirect->status_code }}</span></td>
                            <td class="p-3 font-bold">{{ number_format($redirect->hits) }}</td>
                            <td class="p-3"><span class="rounded-full px-2 py-1 text-xs font-bold {{ $redirect->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $redirect->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="p-3"><div class="flex flex-wrap gap-3"><a href="{{ route('admin.seo.redirects.edit', $redirect) }}" class="font-semibold text-blue-700">Edit</a><form method="POST" action="{{ route('admin.seo.redirects.destroy', $redirect) }}" onsubmit="return confirm('Delete this redirect?')">@csrf @method('DELETE')<button class="font-semibold text-rose-700">Delete</button></form></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-10 text-center text-slate-500">No redirects configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 p-4">{{ $redirects->links() }}</div>
    </section>
</div>
@endsection
