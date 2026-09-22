<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\AdminLoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tək guard + rollar: giriş heç bir rolu rədd etmir, yönləndirmə rola görə olur,
 * başqa rolun paneli isə 403 verir.
 */
class RolePanelTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, string $uri = '/login'): \Illuminate\Testing\TestResponse
    {
        return $this->post($uri, [
            'email' => $user->email,
            'password' => 'password',
        ]);
    }

    public function test_a_student_lands_in_the_student_dashboard(): void
    {
        $student = User::factory()->student()->create();

        $this->login($student)->assertRedirect(route('student.dashboard'));

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk();
    }

    public function test_an_admin_lands_in_the_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->login($admin)->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    /** Bir hesabın həm admin, həm şagird rolu ola bilər: ən yüksək səlahiyyətli panel açılır. */
    public function test_a_user_with_several_roles_lands_in_the_highest_panel(): void
    {
        $user = User::factory()->student()->create();
        $user->assignRole('admin');

        $this->login($user)->assertRedirect(route('admin.dashboard'));

        // Hər iki panel də açıqdır: rol itmir
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('student.dashboard'))->assertOk();
    }

    /** Müəllim modulu söndürülüb: müəllim rolu şagird kabinetinə düşür. */
    public function test_a_teacher_falls_back_to_the_student_panel_while_the_module_is_off(): void
    {
        config(['features.teachers' => false]);

        $user = User::factory()->teacher()->create();
        $user->assignRole('student');

        $this->login($user)->assertRedirect(route('student.dashboard'));
    }

    public function test_a_student_can_not_open_the_admin_panel(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_an_admin_can_not_open_the_student_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('student.dashboard'))->assertForbidden();
    }

    /** Qonaq admin panelinə girəndə admin giriş səhifəsinə göndərilir. */
    public function test_a_guest_is_sent_to_the_admin_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    /** Daxil olmuş istifadəçi giriş səhifələrini açanda öz panelinə qaytarılır. */
    public function test_an_authenticated_user_is_redirected_away_from_the_login_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/login')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get(route('admin.login'))->assertRedirect(route('admin.dashboard'));
    }

    /** Admin girişi: parol düz olsa da, admin olmayan hesab buraxılmır və sessiya açılmır. */
    public function test_the_admin_login_rejects_a_non_admin_account(): void
    {
        $student = User::factory()->student()->create();

        $this->login($student, route('admin.login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** Ehtimal seçmə: uğursuz cəhdlərdən sonra düzgün parol da qəbul edilmir. */
    public function test_the_admin_login_is_rate_limited(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (range(1, AdminLoginRequest::MAX_ATTEMPTS) as $attempt) {
            $this->post(route('admin.login'), [
                'email' => $admin->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $response = $this->login($admin, route('admin.login'));

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString(
            'saniyə',
            session('errors')->first('email'),
        );
    }
}
