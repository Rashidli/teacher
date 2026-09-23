<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Başlıqdakı hesab vəziyyəti.
 *
 * Komponent `auth.user` prop-una baxır: qonaqda giriş/qeydiyyat linkləri, daxil olmuş
 * istifadəçidə isə rola uyğun hesab menyusu göstərilir. Burada həmin prop-un düzgün
 * paylaşıldığı yoxlanılır (render Vue tərəfdədir).
 */
class SiteHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_gets_no_user_in_the_shared_props(): void
    {
        $this->get('/imtahanlar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('auth.user', null));
    }

    public function test_a_logged_in_student_gets_name_and_roles(): void
    {
        $student = User::factory()->create(['first_name' => 'Aysel', 'last_name' => 'Məmmədova']);
        $student->assignRole('student');

        $this->actingAs($student)
            ->get('/imtahanlar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.first_name', 'Aysel')
                ->where('auth.user.full_name', 'Aysel Məmmədova')
                ->where('auth.user.roles', ['student']));
    }

    /** Admin menyusunda "Admin panel" bəndi rola görə görünür. */
    public function test_an_admin_is_marked_in_the_shared_props(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get('/imtahanlar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('auth.user.roles', ['admin']));
    }

    /** Menyudakı bəndlərin route-ları mövcud olmalıdır (komponent onları route() ilə qurur). */
    public function test_the_account_menu_routes_exist(): void
    {
        foreach ([
            'student.exams.index',
            'student.results.index',
            'student.statistics',
            'profile.edit',
            'admin.dashboard',
            'logout',
        ] as $name) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($name), "Route yoxdur: {$name}");
        }
    }

    /** Çıxış yalnız POST ilə — menyudakı bənd `method="post"` ilə göndərilir. */
    public function test_logout_is_post_only(): void
    {
        $methods = \Illuminate\Support\Facades\Route::getRoutes()->getByName('logout')->methods();

        $this->assertContains('POST', $methods);
        $this->assertNotContains('GET', $methods);

        $student = User::factory()->create();
        $student->assignRole('student');

        // GET /logout route-a düşmür və kateqoriya catch-all-ında da uyğunluq tapmır
        $this->actingAs($student)->get('/logout')->assertNotFound();
        $this->assertAuthenticated();
    }
}
