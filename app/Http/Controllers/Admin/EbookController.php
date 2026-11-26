<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ebook;
use Illuminate\Http\Request;

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
        Ebook::create($this->validated($request));
        return redirect()->route('admin.ebooks.index')->with('success', 'E-book created.');
    }

    public function edit(Ebook $ebook)
    {
        return view('admin.ebooks.form', compact('ebook'));
    }

    public function update(Request $request, Ebook $ebook)
    {
        $ebook->update($this->validated($request, $ebook));
        return redirect()->route('admin.ebooks.index')->with('success', 'E-book updated.');
    }

    public function destroy(Ebook $ebook)
    {
        $ebook->delete();
        return back()->with('success', 'E-book deleted.');
    }

    private function validated(Request $request, ?Ebook $ebook = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'pages' => 'nullable|integer|min:0',
            'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:4096',
            'cover_url' => 'nullable|url:http,https|max:2048',
            'file' => 'nullable|file|mimes:pdf,epub|max:51200',
            'file_url' => 'nullable|url:http,https|max:2048',
        ]);

        if ($request->hasFile('cover')) {
            $data['cover'] = $request->file('cover')->store('ebooks', 'public');
        } elseif ($request->filled('cover_url')) {
            $data['cover'] = $data['cover_url'];
        }
        unset($data['cover_url']);

        if ($request->hasFile('file')) {
            $data['file'] = $request->file('file')->store('ebook-files', 'local');
        } elseif ($request->filled('file_url')) {
            $data['file'] = $data['file_url'];
        }
        unset($data['file_url']);

        $data['is_free'] = $request->boolean('is_free');
        $data['is_published'] = $request->boolean('is_published');
        $data['price'] = $data['is_free'] ? 0 : $data['price'];

        return $data;
    }
}
