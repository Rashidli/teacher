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

    /** Müəllim modulu söndürülüb: şagird rolu da olan müəllim kabinetə düşür. */
    public function test_a_teacher_with_a_student_role_falls_back_to_the_student_panel(): void
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

    /**
     * Admin olmayan hesab səhv parolla eyni cavabı almalıdır: fərqli mesaj
     * parolun düz olduğunu açardı.
     */
    public function test_the_admin_login_does_not_reveal_that_the_password_was_right(): void
    {
        $student = User::factory()->student()->create();

        $wrongPassword = $this->post(route('admin.login'), [
            'email' => $student->email,
            'password' => 'wrong-password',
        ]);

        $rightPassword = $this->login($student, route('admin.login'));

        foreach ([$wrongPassword, $rightPassword] as $response) {
            $response->assertRedirect();
            $response->assertSessionHasErrors(['email' => AdminLoginRequest::FAILED_MESSAGE]);
        }

        $this->assertGuest();
    }

    /** Başqa rolla daxil olmuş istifadəçi admin formasını görə bilməlidir. */
    public function test_a_logged_in_non_admin_still_sees_the_admin_login_form(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Auth/Login'));
    }

    /** Həmin formadan admin hesabına keçmək mümkündür. */
    public function test_a_logged_in_student_can_switch_to_an_admin_account(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($student)
            ->post(route('admin.login'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    /** Artıq admin olan istifadəçi forma əvəzinə panelini görür. */
    public function test_an_admin_is_redirected_from_the_admin_login_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.login'))->assertRedirect(route('admin.dashboard'));
    }

    /** Müəllim modulu söndürülü olanda yalnız müəllim rolu olan hesabın paneli yoxdur. */
    public function test_a_teacher_only_account_gets_an_explanation_instead_of_a_403(): void
    {
        config(['features.teachers' => false]);

        $teacher = User::factory()->teacher()->create();

        $this->login($teacher)->assertRedirect(route('no-panel'));

        $this->actingAs($teacher)
            ->get(route('no-panel'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('NoPanel')
                ->where('isTeacher', true));

        // Şagird kabineti yenə də bağlıdır
        $this->actingAs($teacher)->get(route('student.dashboard'))->assertForbidden();
    }

    /** Rolsuz hesab da eyni səhifəyə düşür (403 yox). */
    public function test_an_account_without_roles_gets_the_same_explanation(): void
    {
        $user = User::factory()->create();

        $this->login($user)->assertRedirect(route('no-panel'));

        $this->actingAs($user)
            ->get(route('no-panel'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('NoPanel')->where('isTeacher', false));
    }

    /** Paneli olan hesab bu səhifədə qalmır. */
    public function test_a_user_with_a_panel_is_sent_back_from_the_no_panel_page(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('no-panel'))->assertRedirect(route('student.dashboard'));
    }

    public function test_the_no_panel_page_needs_a_login(): void
    {
        $this->get(route('no-panel'))->assertRedirect(route('login'));
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
