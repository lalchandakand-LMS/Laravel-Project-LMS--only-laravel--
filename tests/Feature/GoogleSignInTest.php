<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function configureGoogle(): void
    {
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
            'services.google.redirect' => 'https://lms.example.test/auth/google/callback',
        ]);
    }

    public function test_google_callback_creates_a_verified_student_account(): void
    {
        $this->configureGoogle();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token'], 200),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-subject-123',
                'email' => 'Learner@example.com',
                'email_verified' => true,
                'name' => 'Google Learner',
            ], 200),
        ]);

        $this->withSession(['google_oauth_state' => 'valid-state'])
            ->get(route('auth.google.callback', ['code' => 'authorization-code', 'state' => 'valid-state']))
            ->assertRedirect(route('student.dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'learner@example.com',
            'role' => 'student',
            'provider' => 'google',
            'provider_id' => 'google-subject-123',
        ]);

        $this->assertNotNull(User::where('email', 'learner@example.com')->firstOrFail()->email_verified_at);
    }

    public function test_google_callback_rejects_a_forged_oauth_state(): void
    {
        $this->configureGoogle();
        Http::preventStrayRequests();

        $this->withSession(['google_oauth_state' => 'expected-state'])
            ->get(route('auth.google.callback', ['code' => 'authorization-code', 'state' => 'attacker-state']))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_callback_rejects_unverified_provider_email(): void
    {
        $this->configureGoogle();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token'], 200),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-subject-456',
                'email' => 'unverified@example.com',
                'email_verified' => false,
                'name' => 'Unverified',
            ], 200),
        ]);

        $this->withSession(['google_oauth_state' => 'valid-state'])
            ->get(route('auth.google.callback', ['code' => 'authorization-code', 'state' => 'valid-state']))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 0);
    }
}
