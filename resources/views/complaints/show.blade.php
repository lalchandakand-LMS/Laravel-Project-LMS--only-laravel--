@extends(auth()->user()->role === 'student' ? 'layouts.student' : (auth()->user()->role === 'admin' ? 'layouts.admin' : 'layouts.portal'))
@section('title', $complaint->tracking_code)
@section('heading', 'Complaint tracking')
@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3"><span class="font-black text-emerald-700">{{ $complaint->tracking_code }}</span><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{ str_replace('_',' ',ucfirst($complaint->status)) }}</span></div>
        <h2 class="mt-3 text-xl font-black text-slate-900">{{ $complaint->subject }}</h2>
        <p class="mt-1 text-xs text-slate-500">{{ ucfirst($complaint->category) }} · Submitted {{ $complaint->created_at->format('d M Y, h:i A') }} · Assigned: {{ $complaint->assignee?->name ?? 'Not assigned yet' }}</p>
        <p class="mt-5 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $complaint->body }}</p>
        @foreach($complaint->attachments as $file)<a class="mt-3 mr-3 inline-block text-xs font-bold text-emerald-700" href="{{ route('complaints.attachments.download',$file) }}">📎 {{ $file->original_name }}</a>@endforeach
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <h3 class="font-bold text-slate-900">Conversation history</h3>
        <div class="mt-4 space-y-3">
            @forelse($complaint->replies as $reply)
                <div class="rounded-xl {{ $reply->user_id === auth()->id() ? 'bg-emerald-50' : 'bg-slate-50' }} p-4">
                    <p class="text-xs font-bold text-slate-800">{{ $reply->user?->name ?? 'Deleted user' }} <span class="font-normal text-slate-400">· {{ $reply->created_at->format('d M Y, h:i A') }}</span></p>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $reply->body }}</p>
                    @foreach($reply->attachments as $file)<a class="mt-2 mr-3 inline-block text-xs font-bold text-emerald-700" href="{{ route('complaints.attachments.download',$file) }}">📎 {{ $file->original_name }}</a>@endforeach
                </div>
            @empty
                <p class="text-sm text-slate-500">No replies yet. Our team will review your complaint.</p>
            @endforelse
        </div>
        @if(!in_array($complaint->status,['closed','rejected'],true))
            <form method="POST" action="{{ route('complaints.reply',$complaint) }}" enctype="multipart/form-data" class="mt-5 space-y-3">@csrf
                <textarea name="body" required minlength="2" maxlength="5000" rows="4" class="w-full rounded-lg border border-slate-300 p-3 text-sm" placeholder="Write a reply..."></textarea>
                <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx" class="text-xs">
                <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Send reply</button>
            </form>
        @else
            <p class="mt-5 text-sm font-semibold text-slate-500">This complaint is closed for further replies.</p>
        @endif
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="font-bold text-slate-900">Case activity</h3><ol class="mt-4 space-y-3">@foreach($complaint->events->where('is_internal', false) as $event)<li class="flex gap-3 text-sm"><span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-emerald-600"></span><div><p class="font-semibold text-slate-700">{{ $event->description }}</p><p class="text-xs text-slate-400">{{ $event->actor?->name ?? 'System' }} · {{ $event->created_at->format('d M Y, h:i A') }}</p></div></li>@endforeach</ol></section>
    <a href="{{ route('complaints.index') }}" class="text-sm font-bold text-emerald-700">← Back to my complaints</a>
</div>
@endsection
