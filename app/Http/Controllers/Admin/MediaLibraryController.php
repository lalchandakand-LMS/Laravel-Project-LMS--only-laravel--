<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaLibraryController extends Controller
{
    public function index(Request $request)
    {
        $assets = MediaAsset::with('uploader')
            ->when($request->filled('q'), fn ($query) => $query->where(function ($search) use ($request) {
                $term = '%'.(string) $request->input('q').'%';
                $search->where('title', 'like', $term)->orWhere('original_name', 'like', $term);
            }))
            ->when($request->input('type') === 'image', fn ($query) => $query->where('mime_type', 'like', 'image/%'))
            ->when($request->input('type') === 'pdf', fn ($query) => $query->where('mime_type', 'application/pdf'))
            ->latest()->paginate(24)->withQueryString();

        return view('admin.media.index', compact('assets'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'alt_text' => 'nullable|string|max:255',
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,gif,pdf|max:25600',
        ]);

        $file = $data['file'];
        $path = $file->store('media-library', 'public');

        MediaAsset::create([
            'title' => $data['title'],
            'alt_text' => $data['alt_text'] ?? null,
            'source_type' => 'upload',
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.media.index')->with('success', 'Media uploaded to the library.');
    }

    public function storeLink(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'alt_text' => 'nullable|string|max:255',
            'url' => ['required', 'url', 'max:2048', 'regex:/^https?:\/\//i'],
            'mime_type' => 'nullable|in:image/jpeg,image/png,image/webp,image/gif,application/pdf',
        ]);

        MediaAsset::create([
            'title' => $data['title'],
            'alt_text' => $data['alt_text'] ?? null,
            'source_type' => 'link',
            'url' => $data['url'],
            'mime_type' => $data['mime_type'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.media.index')->with('success', 'External media link saved.');
    }

    public function destroy(MediaAsset $media)
    {
        if ($media->source_type === 'upload' && $media->disk && $media->path) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $media->delete();

        return redirect()->route('admin.media.index')->with('success', 'Media item removed from the library.');
    }
}
