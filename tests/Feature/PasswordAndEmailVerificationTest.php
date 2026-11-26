<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PasswordAndEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_registration_requires_email_verification_before_entering_the_student_portal(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'New Learner',
            'email' => 'new.learner@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'new.learner@example.com')->firstOrFail();
        $this->assertTrue($user->email_verification_required);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->actingAs($user)->get(route('student.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_guest_can_request_a_password_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewSecurePass123',
            'password_confirmation' => 'NewSecurePass123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewSecurePass123', $user->fresh()->password));
    }

    public function test_invalid_password_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewSecurePass123',
            'password_confirmation' => 'NewSecurePass123',
        ])->assertSessionHasErrors('email');
    }

    public function test_authenticated_user_can_verify_email_using_signed_link(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $this->actingAs($user)->get($url)->assertRedirect($user->dashboardRoute());

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_unverified_user_can_resend_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'))
            ->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
