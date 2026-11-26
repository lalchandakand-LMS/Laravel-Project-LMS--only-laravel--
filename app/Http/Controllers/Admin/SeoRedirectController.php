<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\SeoRedirect;
use Illuminate\\Http\\Request;
use Illuminate\\Validation\\Rule;

class SeoRedirectController extends Controller
{
    public function index(Request $request)
    {
        $redirects = SeoRedirect::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim($request->string('q')->toString());
                $query->where(function ($search) use ($term) {
                    $search->where('source_path', 'like', '%'.$term.'%')
                        ->orWhere('destination_path', 'like', '%'.$term.'%');
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.seo.redirects.index', compact('redirects'));
    }

    public function create()
    {
        return view('admin.seo.redirects.form', ['redirect' => new SeoRedirect(['status_code' => 301, 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        SeoRedirect::create([
            ...$this->validated($request),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.seo.redirects.index')->with('success', 'SEO redirect created.');
    }

    public function edit(SeoRedirect $redirect)
    {
        return view('admin.seo.redirects.form', compact('redirect'));
    }

    public function update(Request $request, SeoRedirect $redirect)
    {
        $redirect->update($this->validated($request, $redirect));

        return redirect()->route('admin.seo.redirects.index')->with('success', 'SEO redirect updated.');
    }

    public function destroy(SeoRedirect $redirect)
    {
        $redirect->delete();

        return redirect()->route('admin.seo.redirects.index')->with('success', 'SEO redirect deleted.');
    }

    private function validated(Request $request, ?SeoRedirect $redirect = null): array
    {
        $source = trim((string) $request->input('source_path', ''));
        if ($source !== '' && ! str_starts_with($source, '/')) {
            $source = '/'.$source;
        }
        $request->merge(['source_path' => $source]);

        return $request->validate([
            'source_path' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^\\/(?!\\/)[A-Za-z0-9._~%\\/-]+$/',
                Rule::unique('seo_redirects', 'source_path')->ignore($redirect?->id),
                function (string $attribute, mixed $value, \\Closure $fail): void {
                    $segments = explode('/', trim((string) $value, '/'));
                    if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
                        $fail('The source path cannot contain dot segments.');
                    }
                },
            ],
            'destination_path' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \\Closure $fail) use ($source): void {
                    if (! is_string($value) || ! str_starts_with($value, '/')
                        || str_starts_with($value, '//')
                        || str_contains($value, '\\\\')
                        || preg_match('/[\\x00-\\x20]/', $value) === 1
                        || str_contains($value, '#')) {
                        $fail('Use a same-site path beginning with a single slash. External URLs, fragments and control characters are not allowed.');
                        return;
                    }

                    $parts = parse_url($value);
                    $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
                    if (! is_array($parts) || ! str_starts_with($path, '/') || str_starts_with($path, '//')
                        || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
                        || $path === $source) {
                        $fail('The destination must be a different internal path.');
                        return;
                    }

                    $segments = explode('/', trim($path, '/'));
                    if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
                        $fail('The destination path cannot contain dot segments.');
                    }
                },
            ],
            'status_code' => ['required', 'integer', Rule::in([301, 302])],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
