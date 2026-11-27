<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use App\Notifications\AdminLoginOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_must_complete_email_otp_before_entering_admin_portal(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('Strong-password-123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->get(route('admin.login'))->assertOk();

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'Strong-password-123',
        ])->assertRedirect(route('admin.login.verify'));

        $this->assertGuest();
        $this->get(route('admin.login.verify'))->assertOk();

        $code = null;
        Notification::assertSentTo($admin, AdminLoginOtpNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $this->post(route('admin.login.verify.store'), ['code' => $code])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_non_admin_cannot_authenticate_through_admin_login(): void
    {
        Notification::fake();

        $student = User::factory()->create([
            'email' => 'student@example.test',
            'password' => Hash::make('Strong-password-123'),
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'email' => $student->email,
            'password' => 'Strong-password-123',
        ])->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        Notification::assertNothingSent();
    }

    public function test_admin_cannot_use_the_public_login_endpoint(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('Strong-password-123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->from(route('login'))->post(route('login'), [
            'email' => $admin->email,
            'password' => 'Strong-password-123',
        ])->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_every_non_admin_role_is_blocked_from_admin_routes(): void
    {
        foreach (['student', 'instructor', 'guardian', 'branch_manager'] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'is_active' => true,
            ]);

            $this->actingAs($user)->get('/admin')->assertForbidden();
            $this->actingAs($user)->get('/admin/students')->assertForbidden();
        }
    }

    public function test_invalid_admin_otp_does_not_authenticate_the_user(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('Strong-password-123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'Strong-password-123',
        ])->assertRedirect(route('admin.login.verify'));

        $this->from(route('admin.login.verify'))->post(route('admin.login.verify.store'), [
            'code' => '000000',
        ])->assertRedirect(route('admin.login.verify'))
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }


    public function test_expired_admin_otp_requires_a_fresh_password_login(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('Strong-password-123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'Strong-password-123',
        ])->assertRedirect(route('admin.login.verify'));

        $this->travel(6)->minutes();

        $this->get(route('admin.login.verify'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_public_registration_cannot_assign_a_privileged_role(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'New Learner',
            'email' => 'new-learner@example.test',
            'phone' => '01700000000',
            'password' => 'Strong-password-123',
            'password_confirmation' => 'Strong-password-123',
            'role' => 'admin',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'email' => 'new-learner@example.test',
            'role' => 'student',
        ]);
    }

    public function test_admin_cannot_bypass_email_otp_through_google_oauth(): void
    {
        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.google.redirect' => 'https://lms.example.test/auth/google/callback',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token'], 200),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-admin-subject',
                'email' => 'admin@example.test',
                'email_verified' => true,
                'name' => 'Admin User',
            ], 200),
        ]);

        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->withSession(['google_oauth_state' => 'expected-oauth-state'])
            ->get(route('auth.google.callback', [
                'code' => 'google-auth-code',
                'state' => 'expected-oauth-state',
            ]))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'provider' => null,
            'provider_id' => null,
        ]);
    }


    public function test_linked_google_identity_for_admin_still_requires_admin_password_and_otp(): void
    {
        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.google.redirect' => 'https://lms.example.test/auth/google/callback',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token'], 200),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'already-linked-admin-subject',
                'email' => 'linked-admin@example.test',
                'email_verified' => true,
                'name' => 'Linked Admin',
            ], 200),
        ]);

        $admin = User::factory()->create([
            'email' => 'linked-admin@example.test',
            'role' => 'admin',
            'provider' => 'google',
            'provider_id' => 'already-linked-admin-subject',
            'is_active' => true,
        ]);

        $this->withSession(['google_oauth_state' => 'expected-oauth-state'])
            ->get(route('auth.google.callback', [
                'code' => 'google-auth-code',
                'state' => 'expected-oauth-state',
            ]))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
            'provider' => 'google',
            'provider_id' => 'already-linked-admin-subject',
        ]);
    }


    public function test_admin_password_reset_does_not_create_an_authenticated_session_and_still_requires_admin_otp(): void
    {
        $admin = User::factory()->create([
            'email' => 'reset-admin@example.test',
            'password' => Hash::make('Old-Strong-Password-123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $token = Password::createToken($admin);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'New-Strong-Password-123',
            'password_confirmation' => 'New-Strong-Password-123',
        ])->assertRedirect(route('login'));

        $this->assertGuest();

        $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'New-Strong-Password-123',
        ])->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_public_registration_ignores_teacher_guardian_and_branch_manager_role_injection(): void
    {
        Notification::fake();

        foreach (['instructor', 'teacher', 'guardian', 'branch_manager'] as $index => $role) {
            $email = 'role-injection-'.$index.'@example.test';

            $this->post(route('register'), [
                'name' => 'Untrusted Signup',
                'email' => $email,
                'phone' => '01700000000',
                'password' => 'Strong-password-123',
                'password_confirmation' => 'Strong-password-123',
                'role' => $role,
            ])->assertRedirect(route('verification.notice'));

            $this->assertDatabaseHas('users', [
                'email' => $email,
                'role' => 'student',
            ]);

            $this->post(route('logout'));
        }
    }

    public function test_demo_database_seeder_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Demo seeding is disabled in production');

        (new DatabaseSeeder())->run();
    }

    public function test_admin_cannot_remove_own_admin_access_from_user_management(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'student',
                'password' => '',
                'is_active' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
            'is_active' => true,
        ]);
    }


    public function test_admin_password_check_before_otp_does_not_authenticate_or_rotate_remember_token(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'email' => 'admin-pre-otp@example.test',
            'password' => Hash::make('Strong-password-123'),
            'remember_token' => 'existing-remember-token',
            'role' => 'admin',
            'is_active' => true,
        ]);

        \\Illuminate\\Support\\Facades\\Event::fake([
            \\Illuminate\\Auth\\Events\\Login::class,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'Strong-password-123',
            'remember' => '1',
        ])->assertRedirect(route('admin.login.verify'));

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'remember_token' => 'existing-remember-token',
        ]);
        \\Illuminate\\Support\\Facades\\Event::assertNotDispatched(\\Illuminate\\Auth\\Events\\Login::class);
        Notification::assertSentTo($admin, AdminLoginOtpNotification::class);
    }

}
