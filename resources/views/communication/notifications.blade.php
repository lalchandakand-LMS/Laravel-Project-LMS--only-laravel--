@extends($layout)
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500">{{ auth()->user()->unreadNotifications()->count() }} unread notification(s)</p>
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
            <button class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Mark all as read</button>
        </form>
    </div>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        @forelse ($notifications as $notification)
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center {{ $notification->read_at ? '' : 'bg-emerald-50/50' }}">
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-slate-900">{{ $notification->data['title'] ?? 'Notification' }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $notification->data['message'] ?? '' }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
                @if (! $notification->read_at)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf
                        <button class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Mark read & open</button>
                    </form>
                @else
                    <span class="text-xs font-semibold text-slate-400">Read</span>
                @endif
            </div>
        @empty
            <p class="p-8 text-center text-sm text-slate-500">You have no notifications yet.</p>
        @endforelse
    </div>
    {{ $notifications->links() }}
</div>
@endsection
