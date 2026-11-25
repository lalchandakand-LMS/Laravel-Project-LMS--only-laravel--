<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintReply;
use App\Models\User;
use App\Notifications\ComplaintUpdateNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $query = Complaint::with(['user', 'assignee', 'branch'])->latest();
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('category')) $query->where('category', $request->string('category'));
        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(fn ($q) => $q->where('tracking_code', 'like', "%{$term}%")
                ->orWhere('subject', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")));
        }
        return view('admin.complaints.index', ['complaints' => $query->paginate(20)->withQueryString()]);
    }

    public function show(Complaint $complaint)
    {
        return view('admin.complaints.show', [
            'complaint' => $complaint->load(['user', 'assignee', 'branch', 'allReplies.user', 'allReplies.attachments', 'attachments']),
            'staff' => User::whereIn('role', ['admin', 'instructor'])->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Complaint $complaint)
    {
        $data = $request->validate([
            'status' => 'required|in:submitted,under_review,in_progress,resolved,rejected,closed',
            'priority' => 'required|in:low,normal,high,urgent',
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->whereIn('role', ['admin', 'instructor'])->where('is_active', true))],
        ]);
        $data['resolved_at'] = $data['status'] === 'resolved' ? ($complaint->resolved_at ?? now()) : null;
        $data['closed_at'] = in_array($data['status'], ['closed', 'rejected'], true) ? ($complaint->closed_at ?? now()) : null;
        $complaint->update($data);
        $complaint->user?->notify(new ComplaintUpdateNotification($complaint, 'Status changed to '.str_replace('_', ' ', $complaint->status).'.'));
        return back()->with('success', 'Complaint updated and complainant notified.');
    }

    public function reply(Request $request, Complaint $complaint)
    {
        $data = $request->validate(['body' => 'required|string|min:2|max:5000', 'is_internal' => 'nullable|boolean']);
        ComplaintReply::create([
            'complaint_id' => $complaint->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_internal' => $request->boolean('is_internal'),
        ]);
        if (! $request->boolean('is_internal')) {
            $complaint->user?->notify(new ComplaintUpdateNotification($complaint, 'The support team replied to your complaint.'));
        }
        return back()->with('success', 'Reply saved.');
    }
}
