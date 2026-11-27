<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_subscriber_must_confirm_email_before_becoming_subscribed(): void
    {
        Mail::fake();

        $response = $this->from('/')->post(route('newsletter.subscribe'), [
            'email' => 'Reader@Example.test',
            'name' => 'Reader',
            'consent' => '1',
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'reader@example.test',
            'status' => 'pending',
        ]);

        $subscriber = NewsletterSubscriber::where('email', 'reader@example.test')->firstOrFail();
        $this->assertNotNull($subscriber->confirmation_token_hash);
        $this->assertNull($subscriber->confirmed_at);
    }

    public function test_subscriber_cannot_be_created_without_explicit_consent(): void
    {
        $this->from('/')->post(route('newsletter.subscribe'), [
            'email' => 'no-consent@example.test',
        ])->assertSessionHasErrors('consent');

        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'no-consent@example.test']);
    }

    public function test_valid_confirmation_token_confirms_subscription_once(): void
    {
        $token = str_repeat('a', 64);
        $subscriber = NewsletterSubscriber::create([
            'email' => 'confirm@example.test',
            'status' => 'pending',
            'confirmation_token_hash' => hash('sha256', $token),
        ]);

        $this->get(route('newsletter.confirm', ['subscriber' => $subscriber, 'token' => $token]))
            ->assertRedirect(route('home'));

        $this->assertDatabaseHas('newsletter_subscribers', [
            'id' => $subscriber->id,
            'status' => 'subscribed',
            'confirmation_token_hash' => null,
        ]);

        $this->get(route('newsletter.confirm', ['subscriber' => $subscriber, 'token' => $token]))
            ->assertNotFound();
    }

    public function test_unsubscribe_requires_a_valid_signed_url(): void
    {
        $subscriber = NewsletterSubscriber::create([
            'email' => 'leave@example.test',
            'status' => 'subscribed',
            'confirmed_at' => now(),
        ]);

        $this->get(route('newsletter.unsubscribe', $subscriber))->assertForbidden();
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $subscriber->id, 'status' => 'subscribed']);
    }
}
