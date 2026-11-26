<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfileDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileDocumentController extends Controller
{
    public function store(Request $request, User $user)
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::in(['birth_certificate', 'nid', 'passport', 'photo', 'certificate', 'cv', 'appointment_letter', 'other'])],
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $profile = $this->profileFor($user);
        $file = $request->file('file');
        $path = $file->store('profile-documents/'.$user->id, 'local');

        if (! is_string($path) || $path === '') {
            return back()->withErrors(['file' => 'The document could not be stored. Please try again.'])->withInput();
        }

        try {
            $profile->documents()->create([
                'document_type' => $data['document_type'],
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'status' => 'pending',
                'uploaded_by' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Document uploaded for verification.');
    }

    public function download(User $user, ProfileDocument $document)
    {
        $profile = $this->profileFor($user);
        abort_unless($document->documentable_type === $profile->getMorphClass() && (int) $document->documentable_id === (int) $profile->getKey(), 404);

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function review(Request $request, User $user, ProfileDocument $document)
    {
        $profile = $this->profileFor($user);
        abort_unless($document->documentable()->is($profile), 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => 'nullable|string|max:2000',
        ]);

        $document->update([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Document verification status updated.');
    }

    public function downloadAdmission(\App\Models\CoachingAdmission $admission, ProfileDocument $document)
    {
        abort_unless(
            $document->documentable_type === $admission->getMorphClass()
            && (int) $document->documentable_id === (int) $admission->getKey(),
            404
        );
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function reviewAdmission(Request $request, \App\Models\CoachingAdmission $admission, ProfileDocument $document)
    {
        abort_unless(
            $document->documentable_type === $admission->getMorphClass()
            && (int) $document->documentable_id === (int) $admission->getKey(),
            404
        );

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => 'nullable|string|max:2000',
        ]);

        $document->update([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Admission document verification updated.');
    }

    private function profileFor(User $user)
    {
        if ($user->isStudent()) {
            return $user->studentProfile()->firstOrCreate([], ['status' => 'active']);
        }

        abort_unless(in_array($user->role, ['instructor', 'branch_manager'], true), 422, 'Documents are available only for student and staff profiles.');

        return $user->staffProfile()->firstOrCreate([], ['employment_status' => 'active']);
    }
}
