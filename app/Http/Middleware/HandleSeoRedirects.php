<?php

namespace App\Http\Middleware;

use App\Models\SeoRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleSeoRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 404) {
                throw $exception;
            }

            $redirect = $this->findRedirect($request);
            if (! $redirect) {
                throw $exception;
            }

            return $this->redirectTo($redirect);
        }

        if ($response->getStatusCode() !== 404) {
            return $response;
        }

        $redirect = $this->findRedirect($request);

        return $redirect ? $this->redirectTo($redirect) : $response;
    }

    private function findRedirect(Request $request): ?SeoRedirect
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true) || $this->isPrivateRequest($request)) {
            return null;
        }

        $sourcePath = '/'.ltrim($request->path(), '/');

        return SeoRedirect::query()
            ->where('source_path', $sourcePath)
            ->where('is_active', true)
            ->first();
    }

    private function redirectTo(SeoRedirect $redirect): Response
    {
        $destination = $redirect->destination_path;
        if (! is_string($destination) || ! str_starts_with($destination, '/') || str_starts_with($destination, '//')) {
            abort(404);
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
