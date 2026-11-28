<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
            'file' => 'nullable|required_without:files|file|mimes:jpg,jpeg,png,webp,gif,pdf|max:25600',
            'files' => 'nullable|array|max:30',
            'files.*' => 'file|mimes:jpg,jpeg,png,webp,gif,pdf|max:25600',
        ]);

        $uploads = array_values(array_filter(array_merge(
            isset($data['file']) ? [$data['file']] : [],
            $data['files'] ?? []
        )));
        if ($uploads === []) {
            throw ValidationException::withMessages(['file' => 'Choose at least one image or PDF to upload.']);
        }

        $storedPaths = [];
        try {
            foreach ($uploads as $file) {
                $path = $file->store('media-library', 'public');
                if (! $path) {
                    throw ValidationException::withMessages(['file' => 'A media file could not be stored.']);
                }

                $storedPaths[] = $path;
                $title = count($uploads) > 1
                    ? $data['title'].' — '.$file->getClientOriginalName()
                    : $data['title'];

                MediaAsset::registerUpload(
                    $title,
                    'public',
                    $path,
                    $file,
                    $request->user()->id,
                    $data['alt_text'] ?? null
                );
            }
        } catch (\\Throwable $exception) {
            MediaAsset::query()->where('disk', 'public')->whereIn('path', $storedPaths)->delete();
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('admin.media.index')->with('success', count($uploads).' media file(s) uploaded to the library.');
    }

    public function storeLink(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'alt_text' => 'nullable|string|max:255',
            'url' => ['required', 'url:http,https', 'max:2048'],
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
        abort_if($media->isReferenced(), 409, 'This media item is in use. Replace or remove its references before deleting it.');

        if ($media->source_type === 'upload' && $media->disk && $media->path) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $media->delete();

        return redirect()->route('admin.media.index')->with('success', 'Media item removed from the library.');
    }
}
