@extends('layouts.admin')
@section('title', 'Menu Settings')
@section('heading', 'Menu Settings')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 class="text-lg font-extrabold text-slate-900">Website navigation</h2><p class="mt-1 text-sm text-slate-500">Create and manage header and footer links. Changes appear on the public website after saving.</p></div>
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ $menus->count() }} menu items</span>
        </div>
        <form method="POST" action="{{ route('admin.menu-settings.store') }}" class="mt-5 grid gap-4 rounded-xl bg-slate-50 p-4 md:grid-cols-2 xl:grid-cols-4">
            @csrf
            <div><label class="mb-1 block text-xs font-bold text-slate-600">Menu name *</label><input name="label" required maxlength="80" class="w-full rounded-lg border-slate-300 text-sm" placeholder="e.g. About us"></div>
            <div><label class="mb-1 block text-xs font-bold text-slate-600">Link / URL *</label><input name="url" required maxlength="2048" class="w-full rounded-lg border-slate-300 text-sm" placeholder="/about or https://example.com"></div>
            <div><label class="mb-1 block text-xs font-bold text-slate-600">Menu location</label><select name="location" class="w-full rounded-lg border-slate-300 text-sm"><option value="header">Header</option><option value="footer">Footer</option></select></div>
            <div><label class="mb-1 block text-xs font-bold text-slate-600">Parent menu</label><select name="parent_id" class="w-full rounded-lg border-slate-300 text-sm"><option value="">Top level</option>@foreach($parents as $parent)<option value="{{ $parent->id }}">{{ $parent->label }} ({{ $parent->location }})</option>@endforeach</select></div>
            <div><label class="mb-1 block text-xs font-bold text-slate-600">Display order</label><input type="number" name="sort_order" min="0" value="10" class="w-full rounded-lg border-slate-300 text-sm"></div>
            <div><label class="mb-1 block text-xs font-bold text-slate-600">Icon (optional)</label><input name="icon" maxlength="60" class="w-full rounded-lg border-slate-300 text-sm" placeholder="e.g. 📚"></div>
            <div class="flex flex-wrap items-center gap-3 text-xs font-semibold text-slate-600 md:col-span-2 xl:col-span-4">
                <label><input type="checkbox" name="is_enabled" value="1" checked> Enabled</label>
                <label><input type="checkbox" name="show_desktop" value="1" checked> Desktop</label>
                <label><input type="checkbox" name="show_mobile" value="1" checked> Mobile</label>
                <label><input type="checkbox" name="open_new_tab" value="1"> Open in new tab</label>
                <button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">+ Add menu</button>
            </div>
        </form>
    </section>

    @foreach(['header' => 'Header menu', 'footer' => 'Footer menu'] as $location => $heading)
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-extrabold text-slate-900">{{ $heading }}</h3><p class="text-xs text-slate-500">Lower display order appears first. Parent menus create dropdown items.</p></div>
        <div class="divide-y divide-slate-100">
        @forelse($menus->where('location', $location) as $item)
            <div class="grid gap-3 p-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                <form id="menu-update-{{ $item->id }}" method="POST" action="{{ route('admin.menu-settings.update', $item) }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @csrf @method('PUT')
                    <div><label class="mb-1 block text-[11px] font-bold text-slate-500">Name</label><input name="label" value="{{ $item->label }}" required maxlength="80" class="w-full rounded-lg border-slate-300 text-sm"></div>
                    <div><label class="mb-1 block text-[11px] font-bold text-slate-500">URL</label><input name="url" value="{{ $item->url }}" required maxlength="2048" class="w-full rounded-lg border-slate-300 text-sm"></div>
                    <div><label class="mb-1 block text-[11px] font-bold text-slate-500">Parent</label><select name="parent_id" class="w-full rounded-lg border-slate-300 text-sm"><option value="">Top level</option>@foreach($parents->where('location', $location)->where('id', '!=', $item->id) as $parent)<option value="{{ $parent->id }}" @selected($item->parent_id === $parent->id)>{{ $parent->label }}</option>@endforeach</select></div>
                    <div class="grid grid-cols-2 gap-2"><div><label class="mb-1 block text-[11px] font-bold text-slate-500">Order</label><input type="number" name="sort_order" min="0" value="{{ $item->sort_order }}" class="w-full rounded-lg border-slate-300 text-sm"></div><div><label class="mb-1 block text-[11px] font-bold text-slate-500">Icon</label><input name="icon" value="{{ $item->icon }}" maxlength="60" class="w-full rounded-lg border-slate-300 text-sm"></div></div>
                    <input type="hidden" name="location" value="{{ $location }}">
                    <div class="flex flex-wrap items-center gap-3 text-xs font-semibold text-slate-600 sm:col-span-2 xl:col-span-4">
                        <label><input type="checkbox" name="is_enabled" value="1" @checked($item->is_enabled)> Enabled</label>
                        <label><input type="checkbox" name="show_desktop" value="1" @checked($item->show_desktop)> Desktop</label>
                        <label><input type="checkbox" name="show_mobile" value="1" @checked($item->show_mobile)> Mobile</label>
                        <label><input type="checkbox" name="open_new_tab" value="1" @checked($item->open_new_tab)> New tab</label>
                        <button form="menu-update-{{ $item->id }}" class="rounded-lg bg-slate-900 px-4 py-2 font-bold text-white">Save changes</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.menu-settings.destroy', $item) }}" onsubmit="return confirm('Delete this menu item? Child items will become top-level.')" class="self-start">@csrf @method('DELETE')<button class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50">Delete</button></form>
            </div>
            @if($item->children->isNotEmpty())
                <div class="bg-slate-50 px-6 py-2 text-xs text-slate-500">Dropdown items: {{ $item->children->pluck('label')->join(' · ') }}</div>
            @endif
        @empty
            <p class="p-6 text-sm text-slate-500">No {{ $location }} menu items yet.</p>
        @endforelse
        </div>
    </section>
    @endforeach
</div>
@endsection
