<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequiredEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = $request->route()?->getName();

        $verificationRoutes = [
            'verification.notice',
            'verification.verify',
            'verification.send',
            'logout',
        ];

        if ($user
            && $user->email_verification_required
            && ! $user->hasVerifiedEmail()
            && ! in_array($routeName, $verificationRoutes, true)) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
