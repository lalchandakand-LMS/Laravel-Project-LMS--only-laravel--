<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $clientId = config('services.google.client_id');
        $redirectUri = config('services.google.redirect');

        abort_unless(filled($clientId) && filled(config('services.google.client_secret')) && filled($redirectUri), 503, 'Google sign-in is not configured.');

        $state = Str::random(64);
        $request->session()->put('google_oauth_state', $state);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request)
    {
        $expectedState = (string) $request->session()->pull('google_oauth_state');
        $receivedState = (string) $request->query('state');

        if ($expectedState === '' || $receivedState === '' || ! hash_equals($expectedState, $receivedState)) {
            throw ValidationException::withMessages(['email' => 'Google sign-in session expired or could not be verified. Please try again.']);
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('login')->withErrors(['email' => 'Google sign-in was cancelled or denied.']);
        }

        $tokenResponse = Http::asForm()->acceptJson()->timeout(10)->post('https://oauth2.googleapis.com/token', [
            'code' => $request->query('code'),
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        if (! $tokenResponse->successful() || ! $tokenResponse->json('access_token')) {
            return redirect()->route('login')->withErrors(['email' => 'Google could not verify this sign-in. Please use email and password or try again.']);
        }

        $profileResponse = Http::withToken($tokenResponse->json('access_token'))
            ->acceptJson()->timeout(10)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        $profile = $profileResponse->json();
        if (! $profileResponse->successful()
            || ! is_array($profile)
            || ! filter_var($profile['email'] ?? null, FILTER_VALIDATE_EMAIL)
            || ($profile['email_verified'] ?? false) !== true
            || ! filled($profile['sub'] ?? null)) {
            return redirect()->route('login')->withErrors(['email' => 'Google did not return a verified email address.']);
        }

        $user = User::where('provider', 'google')->where('provider_id', $profile['sub'])->first();

        if (! $user) {
            $user = User::where('email', Str::lower($profile['email']))->first();

            if ($user && filled($user->provider) && ($user->provider !== 'google' || $user->provider_id !== $profile['sub'])) {
                return redirect()->route('login')->withErrors(['email' => 'This email is already connected to another sign-in provider.']);
            }

            if (! $user) {
                $user = User::create([
                    'name' => Str::limit(trim((string) ($profile['name'] ?? 'Student')), 100, ''),
                    'email' => Str::lower($profile['email']),
                    'password' => Hash::make(Str::random(64)),
                    'role' => 'student',
                    'provider' => 'google',
                    'provider_id' => $profile['sub'],
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);
            } else {
                $user->forceFill([
                    'provider' => 'google',
                    'provider_id' => $profile['sub'],
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ])->save();
            }
        }

        if (! $user->is_active) {
            return redirect()->route('login')->withErrors(['email' => 'Your account has been disabled.']);
        }

        auth()->login($user);
        $request->session()->regenerate();

        return redirect()->intended($user->dashboardRoute())->with('success', 'Signed in with Google.');
    }
}
