<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_sign_in_only_through_the_admin_login_page(): void
    {
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
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_non_admin_cannot_authenticate_through_admin_login(): void
    {
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
        }
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
}
