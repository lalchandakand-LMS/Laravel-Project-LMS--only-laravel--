<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'string', 'max:200'],
        ]);

        if (filled($validated['website'] ?? null)) {
            return back()->with('newsletter_status', 'If the address can be subscribed, a confirmation email will arrive shortly.');
        }

        $email = mb_strtolower(trim($validated['email']));
        $token = Str::random(64);
        $subscriber = NewsletterSubscriber::updateOrCreate(
            ['email' => $email],
            [
                'name' => trim((string) ($validated['name'] ?? '')) ?: null,
                'status' => 'pending',
                'confirmation_token_hash' => hash('sha256', $token),
                'confirmed_at' => null,
                'unsubscribed_at' => null,
                'source' => 'website',
            ]
        );

        $confirmationUrl = route('newsletter.confirm', ['subscriber' => $subscriber->id, 'token' => $token]);

        try {
            Mail::raw(
                "Please confirm your subscription to Alom's English Center updates by opening this link:\n\n{$confirmationUrl}\n\nIf you did not request this, you can ignore this email.",
                function ($message) use ($email) {
                    $message->to($email)->subject("Confirm your Alom's English Center newsletter subscription");
                }
            );
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('newsletter_status', 'If the address can be subscribed, a confirmation email will arrive shortly.');
    }

    public function confirm(Request $request, NewsletterSubscriber $subscriber, string $token)
    {
        abort_unless(
            $subscriber->status === 'pending'
            && $subscriber->confirmation_token_hash
            && hash_equals($subscriber->confirmation_token_hash, hash('sha256', $token)),
            404
        );

        $subscriber->update([
            'status' => 'subscribed',
            'confirmed_at' => now(),
            'confirmation_token_hash' => null,
        ]);

        return redirect()->route('home')->with('success', 'Your newsletter subscription is confirmed.');
    }

    public function unsubscribe(Request $request, NewsletterSubscriber $subscriber)
    {
        abort_unless($request->hasValidSignature(), 403);

        $subscriber->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
            'confirmation_token_hash' => null,
        ]);

        return redirect()->route('home')->with('success', 'You have been unsubscribed from newsletter emails.');
    }
}
