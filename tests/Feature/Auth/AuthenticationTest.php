<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vahid /login yalnız şagird girişidir ("student" guard).
 * Admin /admin/login-dan, müəllim (modul açıq olanda) /teacher/login-dan daxil olur.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->student()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated('student');
        $response->assertRedirect(route('student.dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->student()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest('student');
    }

    /** Şagird rolu olmayan hesab vahid giriş formasından daxil ola bilməz. */
    public function test_non_student_accounts_can_not_use_the_student_login(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('student');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->student()->create();

        $response = $this->actingAs($user, 'student')->post('/logout');

        $this->assertGuest('student');
        $response->assertRedirect('/');
    }
}
