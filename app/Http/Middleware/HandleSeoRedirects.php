<?php

namespace App\\Http\\Middleware;

use App\\Models\\SeoRedirect;
use Closure;
use Illuminate\\Http\\Request;
use Symfony\\Component\\HttpFoundation\\Response;

class HandleSeoRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['GET', 'HEAD'], true)
            || $response->getStatusCode() !== 404
            || $this->isPrivateRequest($request)) {
            return $response;
        }

        $sourcePath = '/'.ltrim($request->path(), '/');
        $redirect = SeoRedirect::query()
            ->where('source_path', $sourcePath)
            ->where('is_active', true)
            ->first();

        if (! $redirect) {
            return $response;
        }

        $destination = $redirect->destination_path;
        if (! is_string($destination) || ! str_starts_with($destination, '/') || str_starts_with($destination, '//')) {
            return $response;
        }

        $redirect->increment('hits');

        return redirect()->to($destination, $redirect->status_code);
    }

    private function isPrivateRequest(Request $request): bool
    {
        return $request->is(
            'admin', 'admin/*',
            'my', 'my/*',
            'teacher', 'teacher/*',
            'guardian', 'guardian/*',
            'branch', 'branch/*',
            'cart', 'checkout', 'checkout/*',
            'messages', 'messages/*',
            'notifications', 'notifications/*',
            'login', 'register', 'reset-password', 'logout'
        );
    }
}
