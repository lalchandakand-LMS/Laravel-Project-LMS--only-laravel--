<?php

namespace App\Http\Controllers;

use App\Models\Ebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EbookController extends Controller
{
    public function index()
    {
        return view('ebooks.index', [
            'ebooks' => Ebook::where('is_published', true)->latest()->paginate(12),
        ]);
    }

    public function show(Ebook $ebook)
    {
        abort_unless($ebook->is_published, 404);
        return view('ebooks.show', [
            'ebook' => $ebook,
            'owned' => $ebook->ownedBy(auth()->user()),
            'related' => Ebook::where('is_published', true)->where('id', '!=', $ebook->id)->take(3)->get(),
        ]);
    }

    public function read(Request $request, Ebook $ebook)
    {
        abort_unless($ebook->is_published && $ebook->ownedBy($request->user()), 403, 'Please purchase this e-book first.');
        return view('ebooks.read', compact('ebook'));
    }

    public function file(Request $request, Ebook $ebook)
    {
        abort_unless($ebook->is_published && $ebook->ownedBy($request->user()), 403, 'Please purchase this e-book first.');
        abort_unless($ebook->file, 404, 'E-book file not found.');

        if (preg_match('/^https?:\\/\\//i', $ebook->file)) {
            return redirect()->away($ebook->file)->withHeaders([
                'Cache-Control' => 'private, no-store, max-age=0',
                'Referrer-Policy' => 'no-referrer',
            ]);
        }

        abort_unless(Storage::disk('local')->exists($ebook->file), 404, 'E-book file not found.');

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($ebook->file);
        }

        return response()->file(Storage::disk('local')->path($ebook->file), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Disposition' => 'inline; filename="'.basename($ebook->file).'"',
        ]);
    }
}
