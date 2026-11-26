<?php

namespace App\Http\Controllers;

use App\Services\Sms\MiMSmsGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PhoneVerificationController extends Controller
{
    public function send(Request $request, MiMSmsGateway $gateway)
    {
        $user = $request->user();
        abort_if(blank($user->phone), 422, 'Save your mobile number in your profile before requesting an OTP.');

        $number = $gateway->normalizeBangladeshNumber($user->phone);
        abort_unless($number, 422, 'Enter a valid Bangladesh mobile number before requesting an OTP.');

        $key = 'phone-verification:'.$user->getKey();
        $code = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(5);

        if (! Cache::add($key, [
            'hash' => Hash::make($code),
            'phone' => $number,
            'attempts' => 0,
            'expires_at' => $expiresAt->timestamp,
        ], $expiresAt)) {
            throw ValidationException::withMessages(['otp' => 'An OTP was already sent. Please wait five minutes before requesting another.']);
        }

        try {
            $gateway->sendTransactional($number, 'Your Alom’s English Center verification code is '.$code.'. It expires in 5 minutes. Do not share this code.');
        } catch (\Throwable $exception) {
            Cache::forget($key);
            report($exception);

            throw ValidationException::withMessages(['otp' => 'We could not send the verification SMS. Check the mobile number or contact the administrator.']);
        }

        return back()->with('success', 'A verification code was sent to your saved mobile number.');
    }

    public function verify(Request $request, MiMSmsGateway $gateway)
    {
        $data = $request->validate(['otp' => ['required', 'digits:6']]);
        $user = $request->user();
        $key = 'phone-verification:'.$user->getKey();
        $challenge = Cache::get($key);

        if (! is_array($challenge) || ! isset($challenge['hash'], $challenge['phone'])) {
            throw ValidationException::withMessages(['otp' => 'The verification code expired. Request a new code.']);
        }

        if (now()->timestamp > (int) ($challenge['expires_at'] ?? 0)) {
            Cache::forget($key);
            throw ValidationException::withMessages(['otp' => 'The verification code expired. Request a new code.']);
        }

        $number = $gateway->normalizeBangladeshNumber($user->phone);
        if (! $number || ! hash_equals((string) $challenge['phone'], $number)) {
            Cache::forget($key);
            throw ValidationException::withMessages(['otp' => 'Your mobile number changed. Request a new code for the current number.']);
        }

        if ((int) ($challenge['attempts'] ?? 0) >= 5) {
            Cache::forget($key);
            throw ValidationException::withMessages(['otp' => 'Too many incorrect attempts. Request a new code.']);
        }

        if (! Hash::check($data['otp'], $challenge['hash'])) {
            $challenge['attempts'] = (int) ($challenge['attempts'] ?? 0) + 1;
            Cache::put($key, $challenge, \Illuminate\Support\Carbon::createFromTimestamp((int) $challenge['expires_at']));
            throw ValidationException::withMessages(['otp' => 'The verification code is incorrect.']);
        }

        $user->forceFill(['phone_verified_at' => now()])->save();
        Cache::forget($key);

        return back()->with('success', 'Your mobile number has been verified successfully.');
    }
}
