<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()->latest()->paginate(20);
        return view('communication.notifications', [
            'notifications' => $notifications,
            'layout' => $this->layout(),
        ]);
    }

    public function read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('notifications.index'));
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        return back()->with('success', 'All notifications marked as read.');
    }

    private function layout(): string
    {
        return match (auth()->user()->role) {
            'admin' => 'layouts.admin',
            'student' => 'layouts.student',
            default => 'layouts.portal',
        };
    }
}
