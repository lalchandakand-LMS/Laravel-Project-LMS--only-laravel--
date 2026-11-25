@extends($layout)
@section('title', 'Messages')
@section('heading', 'Messages')
@section('content')
<div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 p-4">
            <h2 class="font-bold text-slate-900">Inbox & sent messages</h2>
            <p class="mt-1 text-xs text-slate-500">Unread messages remain highlighted until you mark them as read.</p>
        </div>
        @forelse ($messages as $message)
            @php($incoming = $message->recipient_id === auth()->id())
            <article class="border-b border-slate-100 p-4 {{ $incoming && ! $message->read_at ? 'bg-emerald-50/60' : '' }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-bold text-slate-900">
                        {{ $incoming ? 'From: '.$message->sender->name : 'To: '.$message->recipient->name }}
                    </p>
                    <span class="text-xs text-slate-400">{{ $message->created_at->format('d M Y, h:i A') }}</span>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $message->body }}</p>
                @if ($incoming)
                    @if ($message->read_at)
                        <p class="mt-2 text-xs font-semibold text-slate-400">Read {{ $message->read_at->diffForHumans() }}</p>
                    @else
                        <form method="POST" action="{{ route('messages.read', $message) }}" class="mt-3">@csrf
                            <button class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white">Mark as read</button>
                        </form>
                    @endif
                @else
                    <p class="mt-2 text-xs text-slate-400">Sent message</p>
                @endif
            </article>
        @empty
            <p class="p-8 text-center text-sm text-slate-500">No messages yet.</p>
        @endforelse
        <div class="p-4">{{ $messages->links() }}</div>
    </section>

    <aside class="h-fit rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="font-bold text-slate-900">Compose message</h2>
        <form method="POST" action="{{ route('messages.store') }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label class="mb-1 block text-xs font-bold text-slate-600">Recipient</label>
                <select name="recipient_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Choose a user</option>
                    @foreach ($recipients as $recipient)
                        <option value="{{ $recipient->id }}" @selected(old('recipient_id') == $recipient->id)>{{ $recipient->name }} — {{ \App\Models\User::ROLES[$recipient->role] ?? $recipient->role }}</option>
                    @endforeach
                </select>
                @error('recipient_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold text-slate-600">Message</label>
                <textarea name="body" rows="6" maxlength="5000" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Write your message...">{{ old('body') }}</textarea>
                @error('body')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <button class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Send message</button>
        </form>
    </aside>
</div>
@endsection
