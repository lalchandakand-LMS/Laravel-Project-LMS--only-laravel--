@extends('layouts.admin')
@section('title', 'Identity card — '.$user->name)
@section('heading', 'Printable identity card')
@section('actions')
    <button type="button" onclick="window.print()" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Print ID card</button>
@endsection
@section('content')
<div class="mx-auto max-w-xl rounded-3xl border-2 border-slate-800 bg-white p-7 shadow-lg print:shadow-none">
    <div class="flex items-center justify-between border-b-2 border-emerald-700 pb-4">
        <div><p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-700">Alom's English Center</p><h2 class="mt-1 text-xl font-black text-slate-900">IDENTITY CARD</h2></div>
        <div class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">{{ $user->role === 'student' ? 'STUDENT' : 'STAFF' }}</div>
    </div>
    <div class="mt-6 flex items-center gap-5">
        <img src="{{ $user->avatarUrl() }}" alt="" class="h-24 w-24 rounded-xl border object-cover">
        <div class="min-w-0 flex-1">
            <h3 class="text-xl font-extrabold text-slate-900">{{ $user->name }}</h3>
            <p class="mt-1 text-sm text-slate-600">{{ $user->roleLabel() }}</p>
            <p class="mt-2 text-sm font-bold text-emerald-800">{{ $user->studentProfile?->student_code ?? $user->staffProfile?->employee_code ?? 'ID pending' }}</p>
            <p class="text-xs text-slate-500">{{ $user->branch?->name ?? "Alom's English Center" }}</p>
        </div>
        <div class="text-center"><div id="identity-qr" class="inline-block"></div><p class="mt-1 text-[10px] text-slate-500">Scan to verify</p></div>
    </div>
    <div class="mt-6 grid grid-cols-2 gap-3 border-t pt-4 text-xs">
        <div><span class="text-slate-500">Mobile</span><p class="font-bold">{{ $user->phone ?: '—' }}</p></div>
        <div><span class="text-slate-500">Phone verification</span><p class="font-bold">{{ $user->phone_verified_at ? 'Verified' : 'Not verified' }}</p></div>
        <div><span class="text-slate-500">Status</span><p class="font-bold">{{ $user->is_active ? 'Active' : 'Disabled' }}</p></div>
        <div><span class="text-slate-500">Issued</span><p class="font-bold">{{ now()->format('d M Y') }}</p></div>
    </div>
    <p class="mt-5 border-t pt-3 text-center text-[10px] text-slate-500">This card is the property of Alom's English Center. If found, please return it to the nearest branch.</p>
</div>
<p class="mx-auto mt-4 max-w-xl text-xs text-slate-500 print:hidden">The QR code is generated in this browser. It contains a random verification link, not the holder's personal details.</p>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" referrerpolicy="no-referrer"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.QRCode) {
        new QRCode(document.getElementById('identity-qr'), {
            text: @json($verificationUrl),
            width: 112,
            height: 112,
            correctLevel: QRCode.CorrectLevel.M
        });
    }
});
</script>
<style>
@media print {
    @page { size: A5 landscape; margin: 10mm; }
    body * { visibility: hidden !important; }
    body main, body main * { visibility: visible !important; }
    body main { position: absolute; inset: 0; width: 100%; }
}
</style>
@endsection
