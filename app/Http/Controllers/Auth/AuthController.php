<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminLoginOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'student',
            'email_verification_required' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice')
            ->with('success', "Welcome to ".config('app.name', "Alom's English Center")."!");
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function showAdminLogin()
    {
        return view('auth.admin-login');
    }

    public function adminLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $throttleKey = 'admin|'.Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again in '.$seconds.' seconds.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $user = Auth::user();

        if (! $user->isAdmin() || ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors(['email' => 'You are not authorized to access the Admin portal.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $code = (string) random_int(100000, 999999);
        $request->session()->put('admin_login_challenge', [
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5)->timestamp,
            'remember' => $request->boolean('remember'),
        ]);

        $user->notify(new AdminLoginOtpNotification($code));
        Auth::logout();

        return redirect()->route('admin.login.verify')
            ->with('success', 'A one-time sign-in code has been sent to your administrator email address.');
    }

    public function showAdminLoginVerify(Request $request)
    {
        $challenge = $request->session()->get('admin_login_challenge');

        if (! $challenge || ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('admin_login_challenge');

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Your sign-in challenge expired. Please sign in again.']);
        }

        return view('auth.admin-login-verify');
    }

    public function verifyAdminLogin(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $challenge = $request->session()->get('admin_login_challenge');

        if (! $challenge || ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('admin_login_challenge');

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Your sign-in challenge expired. Please sign in again.']);
        }

        $throttleKey = 'admin-otp|'.$challenge['user_id'].'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $request->session()->forget('admin_login_challenge');

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Too many verification attempts. Please sign in again.']);
        }

        if (! Hash::check($data['code'], $challenge['code_hash'])) {
            RateLimiter::hit($throttleKey, 300);

            return back()->withErrors(['code' => 'The verification code is invalid.']);
        }

        $user = User::find($challenge['user_id']);

        if (! $user || ! $user->isAdmin() || ! $user->is_active) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'This administrator account is no longer authorized.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->forget('admin_login_challenge');
        Auth::login($user, (bool) ($challenge['remember'] ?? false));
        $request->session()->regenerate();

        if ($user->email_verification_required && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->intended(route('admin.dashboard'))
            ->with('success', 'Welcome back, '.$user->name.'!');
    }

    public function resendAdminLoginOtp(Request $request)
    {
        $challenge = $request->session()->get('admin_login_challenge');
        $user = $challenge ? User::find($challenge['user_id'] ?? null) : null;

        if (! $user || ! $user->isAdmin() || ! $user->is_active) {
            $request->session()->forget('admin_login_challenge');

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Please sign in again to request a new code.']);
        }

        $throttleKey = 'admin-otp-resend|'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            return back()->withErrors(['code' => 'Please wait before requesting another code.']);
        }

        RateLimiter::hit($throttleKey, 60);
        RateLimiter::clear('admin-otp|'.$user->id.'|'.$request->ip());
        $code = (string) random_int(100000, 999999);
        $challenge['code_hash'] = Hash::make($code);
        $challenge['expires_at'] = now()->addMinutes(5)->timestamp;
        $request->session()->put('admin_login_challenge', $challenge);
        $user->notify(new AdminLoginOtpNotification($code));

        return back()->with('success', 'A new verification code has been sent.');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Limit failed password attempts per normalized email + client IP. This
        // avoids a single noisy account blocking every learner behind a shared IP.
        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again in '.$seconds.' seconds.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);

        if (Auth::user()->isAdmin()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Please use the secure Admin login page.']);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Your account has been disabled.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(Auth::user()->dashboardRoute())
            ->with('success', 'Welcome back, '.Auth::user()->name.'!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
