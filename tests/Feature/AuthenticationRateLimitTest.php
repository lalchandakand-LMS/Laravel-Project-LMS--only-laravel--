<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (['student@example.test', 'another@example.test'] as $email) {
            RateLimiter::clear($this->throttleKey($email));
        }

        parent::tearDown();
    }

    private function throttleKey(string $email): string
    {
        return Str::transliterate(Str::lower($email).'|127.0.0.1');
    }

    public function test_password_login_is_throttled_after_five_failed_attempts_for_the_same_email_and_ip(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'student',
            'is_active' => true,
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from(route('login'))->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
        }

        $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString('Too many login attempts.', session('errors')->first('email'));
        $this->assertFalse(auth()->check());
        $this->assertSame(5, RateLimiter::attempts($this->throttleKey($user->email)));
    }

    public function test_successful_login_clears_previous_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'student',
            'is_active' => true,
        ]);

        foreach (range(1, 4) as $attempt) {
            RateLimiter::hit($this->throttleKey($user->email), 60);
        }

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, RateLimiter::attempts($this->throttleKey($user->email)));
    }

    public function test_failed_attempts_for_another_email_do_not_consume_this_accounts_limit(): void
    {
        $first = User::factory()->create([
            'email' => 'student@example.test',
            'password' => Hash::make('correct-password'),
            'role' => 'student',
            'is_active' => true,
        ]);

        foreach (range(1, 5) as $attempt) {
            RateLimiter::hit($this->throttleKey('another@example.test'), 60);
        }

        $this->post(route('login'), [
            'email' => $first->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($first);
    }
}
