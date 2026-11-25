@auth
<div class="flex items-center gap-2" aria-label="Communication">
    <a href="{{ route('notifications.index') }}" class="relative rounded-lg border border-slate-200 px-2.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" aria-label="Notifications">
        🔔 <span class="ml-1">Notifications</span>
        <span data-unread-badge="notifications" class="{{ auth()->user()->unreadNotifications()->count() ? '' : 'hidden' }} ml-1 rounded-full bg-rose-600 px-1.5 py-0.5 text-[10px] font-extrabold text-white">{{ auth()->user()->unreadNotifications()->count() }}</span>
    </a>
    <a href="{{ route('messages.index') }}" class="relative rounded-lg border border-slate-200 px-2.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" aria-label="Messages">
        ✉️ <span class="ml-1">Messages</span>
        <span data-unread-badge="messages" class="{{ auth()->user()->receivedMessages()->whereNull('read_at')->count() ? '' : 'hidden' }} ml-1 rounded-full bg-rose-600 px-1.5 py-0.5 text-[10px] font-extrabold text-white">{{ auth()->user()->receivedMessages()->whereNull('read_at')->count() }}</span>
    </a>
</div>
<script>
(function () {
    const endpoint = @json(route('communication.unread-counts'));
    const badges = document.querySelectorAll('[data-unread-badge]');
    async function refreshUnreadCounts() {
        try {
            const response = await fetch(endpoint, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
            if (!response.ok) return;
            const counts = await response.json();
            badges.forEach((badge) => {
                const count = Number(counts[badge.dataset.unreadBadge] || 0);
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.classList.toggle('hidden', count < 1);
            });
        } catch (_) {}
    }
    window.setInterval(refreshUnreadCounts, 10000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshUnreadCounts(); });
})();
</script>
@endauth
