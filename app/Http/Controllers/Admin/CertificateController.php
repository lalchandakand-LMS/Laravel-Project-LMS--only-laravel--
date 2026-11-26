<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $query = Certificate::with(['user', 'course'])->latest('issued_at');
        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($term) {
                $builder->where('code', 'like', '%'.$term.'%')
                    ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.$term.'%'))
                    ->orWhereHas('course', fn ($courses) => $courses->where('title', 'like', '%'.$term.'%'));
            });
        }

        return view('admin.certificates.index', [
            'certificates' => $query->paginate(20)->withQueryString(),
            'search' => $request->query('q', ''),
        ]);
    }
}
