<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($request->user()->dashboardRoute());
        }

        $request->fulfill();
        $request->user()->forceFill(['email_verification_required' => false])->save();

        return redirect()->intended($request->user()->dashboardRoute())
            ->with('success', 'Your email address has been verified.');
    }
}
