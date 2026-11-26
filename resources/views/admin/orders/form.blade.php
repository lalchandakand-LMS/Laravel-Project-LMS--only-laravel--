@extends('layouts.admin')
@section('title', $order->exists ? 'Edit order '.$order->order_no : 'Create order')
@section('heading', $order->exists ? 'Edit order '.$order->order_no : 'Create order')
@section('content')
@php
    $initialItems = old('items', $order->exists
        ? $order->items->map(fn ($item) => ['type' => $item->item_type, 'id' => $item->item_id])->all()
        : [['type' => 'course', 'id' => '']]);
@endphp
<form method="POST" action="{{ $order->exists ? route('admin.orders.update', $order->order_no) : route('admin.orders.store') }}" class="space-y-6">
    @csrf
    @if ($order->exists) @method('PUT') @endif
    <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="font-bold text-slate-900">Customer and branch</h2>
        @if (!$order->exists)
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">Student
                    <select name="user_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">Select student</option>
                        @foreach ($students as $student)<option value="{{ $student->id }}" @selected(old('user_id') == $student->id)>{{ $student->name }} · {{ $student->email }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Branch
                    <select name="branch_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">Select branch</option>
                        @foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach
                    </select>
                </label>
            </div>
        @else
            <p class="mt-1 text-xs text-slate-500">Student and branch ownership are fixed after order creation.</p>
        @endif
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="text-sm font-medium text-slate-700">Customer name<input name="name" value="{{ old('name', $order->name) }}" required maxlength="100" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Phone<input name="phone" value="{{ old('phone', $order->phone) }}" required maxlength="30" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Email<input type="email" name="email" value="{{ old('email', $order->email) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Address<input name="address" value="{{ old('address', $order->address) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
        </div>
        <label class="mt-4 block text-sm font-medium text-slate-700">Internal note<textarea name="note" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('note', $order->note) }}</textarea></label>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <div class="flex items-center justify-between gap-3"><h2 class="font-bold text-slate-900">Order items</h2><button type="button" id="add-order-line" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">+ Add item</button></div>
        <p class="mt-1 text-xs text-slate-500">Prices are taken from current published catalogue records. Editing is available only before any payment is submitted; changing items resets the discount to zero.</p>
        <div id="order-lines" class="mt-4 space-y-3">
            @foreach ($initialItems as $i => $line)
                <div class="order-line grid gap-3 sm:grid-cols-[1fr_2fr_auto]">
                    <select name="items[{{ $i }}][type]" required class="item-type rounded-lg border border-slate-300 px-3 py-2">
                        <option value="course" @selected(($line['type'] ?? 'course') === 'course')>Course</option>
                        <option value="ebook" @selected(($line['type'] ?? '') === 'ebook')>E-book</option>
                    </select>
                    <select name="items[{{ $i }}][id]" required class="item-id rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">Select item</option>
                        @foreach ($courses as $course)<option data-type="course" value="{{ $course->id }}" @selected(($line['type'] ?? '') === 'course' && (string) ($line['id'] ?? '') === (string) $course->id)>{{ $course->title }} · ৳{{ number_format($course->final_price, 2) }}</option>@endforeach
                        @foreach ($ebooks as $ebook)<option data-type="ebook" value="{{ $ebook->id }}" @selected(($line['type'] ?? '') === 'ebook' && (string) ($line['id'] ?? '') === (string) $ebook->id)>{{ $ebook->title }} · ৳{{ number_format($ebook->price, 2) }}</option>@endforeach
                    </select>
                    <button type="button" class="remove-line rounded-lg border border-rose-200 px-3 py-2 text-sm text-rose-700">Remove</button>
                </div>
            @endforeach
        </div>
    </section>
    <script>
        (() => {
            const wrap = document.getElementById('order-lines');
            const add = document.getElementById('add-order-line');
            const template = wrap.firstElementChild.cloneNode(true);
            const filter = (row) => {
                const type = row.querySelector('.item-type').value;
                const select = row.querySelector('.item-id');
                [...select.options].forEach((option, i) => { if (i) option.hidden = option.dataset.type !== type; });
                if (select.selectedOptions[0]?.hidden) select.value = '';
            };
            const renumber = () => [...wrap.children].forEach((row, i) => {
                row.querySelector('.item-type').name = `items[${i}][type]`;
                row.querySelector('.item-id').name = `items[${i}][id]`;
            });
            wrap.addEventListener('change', (event) => { if (event.target.matches('.item-type')) filter(event.target.closest('.order-line')); });
            wrap.addEventListener('click', (event) => {
                if (event.target.matches('.remove-line')) {
                    if (wrap.children.length > 1) event.target.closest('.order-line').remove();
                    renumber();
                }
            });
            add.addEventListener('click', () => {
                const row = template.cloneNode(true);
                row.querySelector('.item-type').value = 'course';
                row.querySelector('.item-id').value = '';
                wrap.appendChild(row); filter(row); renumber();
            });
            [...wrap.children].forEach(filter);
        })();
    </script>

    <div class="flex flex-wrap gap-2">
        <button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">{{ $order->exists ? 'Save order changes' : 'Create unpaid order' }}</button>
        <a href="{{ $order->exists ? route('admin.orders.show', $order->order_no) : route('admin.orders.index') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
    </div>
</form>
@endsection
