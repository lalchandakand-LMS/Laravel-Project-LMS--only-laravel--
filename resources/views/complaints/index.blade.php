@extends(auth()->user()->role === 'student' ? 'layouts.student' : (auth()->user()->role === 'admin' ? 'layouts.admin' : 'layouts.portal'))
@section('title', 'My complaints')
@section('heading', 'Complaint Center')
@section('content')
<div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
    <section class="space-y-3">
        @forelse($complaints as $complaint)
            <a href="{{ route('complaints.show', $complaint) }}" class="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2"><span class="text-xs font-black text-emerald-700">{{ $complaint->tracking_code }}</span><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold">{{ str_replace('_',' ',ucfirst($complaint->status)) }}</span></div>
                <h2 class="mt-2 font-bold text-slate-900">{{ $complaint->subject }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ ucfirst($complaint->category) }} · {{ $complaint->created_at->format('d M Y') }}</p>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">You have not submitted any complaints yet.</div>
        @endforelse
        {{ $complaints->links() }}
    </section>
    <aside class="h-fit rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="font-bold text-slate-900">Submit a complaint</h2>
        <form method="POST" action="{{ route('complaints.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">@csrf
            <div><label class="text-xs font-bold">Category</label><select name="category" required class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">@foreach(['teacher'=>'Teacher','class'=>'Class','course'=>'Course','payment'=>'Payment','administration'=>'Administration','technical'=>'Technical issue','other'=>'Other'] as $v=>$label)<option value="{{ $v }}">{{ $label }}</option>@endforeach</select></div>
            <div><label class="text-xs font-bold">Subject</label><input name="subject" maxlength="180" required class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm" value="{{ old('subject') }}"></div>
            <div><label class="text-xs font-bold">Details</label><textarea name="body" rows="6" required minlength="10" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">{{ old('body') }}</textarea></div>
            <div><label class="text-xs font-bold">Attachments (optional, max 4 files; 5 MB each)</label><input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx" class="mt-1 w-full text-xs"></div>
            <button class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Submit complaint</button>
        </form>
    </aside>
</div>
@endsection
