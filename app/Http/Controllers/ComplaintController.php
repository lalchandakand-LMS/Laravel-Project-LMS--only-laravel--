<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintReply;
use App\Models\ComplaintEvent;
use App\Notifications\ComplaintUpdateNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $complaints = Complaint::where('user_id', $request->user()->id)->latest()->paginate(15);
        return view('complaints.index', compact('complaints'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|in:teacher,class,course,payment,administration,technical,other',
            'subject' => 'required|string|max:180',
            'body' => 'required|string|min:10|max:10000',
            'attachments' => 'nullable|array|max:4',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
        ]);

        $complaint = Complaint::create([
            'tracking_code' => 'AEC-'.now()->format('ym').'-'.Str::upper(Str::random(8)),
            'user_id' => $request->user()->id,
            'branch_id' => $request->user()->branch_id,
            'category' => $data['category'],
            'subject' => $data['subject'],
            'body' => $data['body'],
        ]);

        ComplaintEvent::create(['complaint_id' => $complaint->id, 'created_by' => $request->user()->id, 'event_type' => 'submitted', 'description' => 'Complaint submitted.']);

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('complaints/'.$complaint->id, 'local');
            ComplaintAttachment::create([
                'complaint_id' => $complaint->id,
                'uploaded_by' => $request->user()->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $admins = \App\Models\User::where('role', 'admin')->where('is_active', true)->get();
        foreach ($admins as $admin) $admin->notify(new ComplaintUpdateNotification($complaint, 'A new complaint was submitted.'));

        return redirect()->route('complaints.show', $complaint)->with('success', 'Your complaint was submitted. Keep the tracking ID for follow-up.');
    }

    public function show(Request $request, Complaint $complaint)
    {
        $this->authorizeOwner($request, $complaint);
        $complaint->load(['user', 'assignee', 'replies.user', 'replies.attachments', 'attachments', 'events']);
        return view('complaints.show', compact('complaint'));
    }

    public function reply(Request $request, Complaint $complaint)
    {
        $this->authorizeOwner($request, $complaint);
        abort_if(in_array($complaint->status, ['closed', 'rejected'], true), 403, 'This complaint is closed for replies.');
        $data = $request->validate([
            'body' => 'required|string|min:2|max:5000',
            'attachments' => 'nullable|array|max:4',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
        ]);
        $reply = ComplaintReply::create([
            'complaint_id' => $complaint->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);
        $this->storeReplyFiles($request, $complaint, $reply);
        ComplaintEvent::create(['complaint_id' => $complaint->id, 'created_by' => $request->user()->id, 'event_type' => 'reply', 'description' => 'A reply was added.']);

        $admins = \App\Models\User::where('role', 'admin')->where('is_active', true)->get();
        foreach ($admins as $admin) $admin->notify(new ComplaintUpdateNotification($complaint, 'The complainant added a reply.'));

        return back()->with('success', 'Reply added.');
    }

    public function attachment(Request $request, ComplaintAttachment $attachment)
    {
        $complaint = $attachment->complaint;
        $this->authorizeOwner($request, $complaint);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    private function authorizeOwner(Request $request, Complaint $complaint): void
    {
        abort_unless($request->user()->isAdmin() || $complaint->user_id === $request->user()->id, 403);
    }

    private function storeReplyFiles(Request $request, Complaint $complaint, ComplaintReply $reply): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('complaints/'.$complaint->id, 'local');
            ComplaintAttachment::create([
                'complaint_id' => $complaint->id,
                'reply_id' => $reply->id,
                'uploaded_by' => $request->user()->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }
}
