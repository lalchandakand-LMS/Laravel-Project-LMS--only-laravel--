<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EbookController extends Controller
{
    public function index()
    {
        return view('admin.ebooks.index', ['ebooks' => Ebook::withCount('purchases')->latest()->paginate(15)]);
    }

    public function create()
    {
        return view('admin.ebooks.form', ['ebook' => new Ebook(['is_published' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $stored = [];

        try {
            foreach (['cover' => 'public', 'file' => 'local'] as $field => $disk) {
                if ($request->hasFile($field)) {
                    $path = $request->file($field)->store($field === 'cover' ? 'ebooks' : 'ebook-files', $disk);
                    if (! $path) {
                        throw ValidationException::withMessages([
                            $field => 'The uploaded '.$field.' could not be stored. Existing e-book files have been kept.',
                        ]);
                    }
                    $data[$field] = $path;
                    $stored[] = [$disk, $path];
                } elseif ($request->filled($field.'_url')) {
                    $data[$field] = $data[$field.'_url'];
                }
                unset($data[$field.'_url']);
            }

            Ebook::create($data);
        } catch (\Throwable $exception) {
            foreach ($stored as [$disk, $path]) {
                delete_stored_media($disk, $path);
            }
            throw $exception;
        }

        return redirect()->route('admin.ebooks.index')->with('success', 'E-book created.');
    }

    public function edit(Ebook $ebook)
    {
        return view('admin.ebooks.form', compact('ebook'));
    }

    public function update(Request $request, Ebook $ebook)
    {
        $data = $this->validated($request, $ebook);

        if ($this->hasSales($ebook)) {
            if ($request->hasFile('file')) {
                throw ValidationException::withMessages([
                    'file' => 'This e-book has purchases or order history. Its PDF/EPUB cannot be replaced because existing buyers must retain the exact purchased content.',
                ]);
            }

            if ($request->filled('file_url') && $request->input('file_url') !== $ebook->file) {
                throw ValidationException::withMessages([
                    'file_url' => 'This e-book has purchases or order history. Its PDF/EPUB link cannot be changed because existing buyers must retain the exact purchased content.',
                ]);
            }
        }

        $oldCover = $ebook->cover;
        $oldFile = $ebook->file;
        $stored = [];

        try {
            foreach (['cover' => 'public', 'file' => 'local'] as $field => $disk) {
                if ($request->hasFile($field)) {
                    $path = $request->file($field)->store($field === 'cover' ? 'ebooks' : 'ebook-files', $disk);
                    if (! $path) {
                        throw ValidationException::withMessages([
                            $field => 'The uploaded '.$field.' could not be stored. Existing e-book files have been kept.',
                        ]);
                    }
                    $data[$field] = $path;
                    $stored[] = [$disk, $path];
                } elseif ($request->filled($field.'_url')) {
                    $data[$field] = $data[$field.'_url'];
                }
                unset($data[$field.'_url']);
            }

            $ebook->update($data);
        } catch (\Throwable $exception) {
            foreach ($stored as [$disk, $path]) {
                delete_stored_media($disk, $path);
            }
            throw $exception;
        }

        if (isset($data['cover']) && $data['cover'] !== $oldCover) {
            delete_stored_media('public', $oldCover);
        }
        if (isset($data['file']) && $data['file'] !== $oldFile && ! $this->hasSales($ebook)) {
            delete_stored_media('local', $oldFile);
        }

        return redirect()->route('admin.ebooks.index')->with('success', 'E-book updated.');
    }

    public function destroy(Ebook $ebook)
    {
        $ebook->delete();

        return redirect()->route('admin.ebooks.index')->with(
            'success',
            'E-book archived. Its files and purchase history are preserved. Restore it from Recycle Bin if needed.'
        );
    }

    private function hasSales(Ebook $ebook): bool
    {
        return $ebook->purchases()->exists()
            || DB::table('order_items')
                ->where('item_type', 'ebook')
                ->where('item_id', $ebook->getKey())
                ->exists();
    }

    private function validated(Request $request, ?Ebook $ebook = null): array
    {
        // HTML checkboxes are omitted when unchecked; persist their false state explicitly.
        $request->merge([
            'is_free' => $request->boolean('is_free'),
            'is_published' => $request->boolean('is_published'),
        ]);

        $data = $request->validate([
            'title' => 'required|string|max:180',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'pages' => 'nullable|integer|min:0',
            'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:4096',
            'cover_url' => 'nullable|url:http,https|max:2048',
            'file' => 'nullable|file|mimes:pdf,epub|max:51200',
            'file_url' => 'nullable|url:https|max:2048',
            'is_free' => 'required|boolean',
            'is_published' => 'required|boolean',
        ]);

        if (! $data['is_free'] && ! empty($data['file_url']) && $data['file_url'] !== ($ebook?->file)) {
            throw ValidationException::withMessages([
                'file_url' => 'Paid e-books must use a privately stored PDF/EPUB upload. Public hosted URLs cannot be protected by purchase access.',
            ]);
        }

        return $data;
    }
}
