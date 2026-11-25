<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $messages = Message::with(['sender', 'recipient'])
            ->where(fn ($query) => $query->where('sender_id', $user->id)->orWhere('recipient_id', $user->id))
            ->latest()->paginate(30);

        $recipients = User::where('is_active', true)->whereKeyNot($user->id)
            ->orderBy('name')->get(['id', 'name', 'role']);

        return view('communication.messages', [
            'messages' => $messages,
            'recipients' => $recipients,
            'layout' => match ($user->role) {
                'admin' => 'layouts.admin',
                'student' => 'layouts.student',
                default => 'layouts.portal',
            },
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id', 'different:sender_id'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        abort_if((int) $data['recipient_id'] === (int) $request->user()->id, 422, 'You cannot message yourself.');

        $recipient = User::where('is_active', true)->findOrFail($data['recipient_id']);
        $message = Message::create([
            'sender_id' => $request->user()->id,
            'recipient_id' => $recipient->id,
            'body' => $data['body'],
        ]);

        $recipient->notify(new NewMessageNotification($message->load('sender')));

        return redirect()->route('messages.index')->with('success', 'Message sent.');
    }

    public function read(Request $request, Message $message)
    {
        abort_unless($message->recipient_id === $request->user()->id, 403);
        if (! $message->read_at) {
            $message->update(['read_at' => now()]);
        }

        return back()->with('success', 'Message marked as read.');
    }

    public function unreadCounts(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'notifications' => $user->unreadNotifications()->count(),
            'messages' => Message::where('recipient_id', $user->id)->whereNull('read_at')->count(),
        ]);
    }
}
