<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Kateqoriya səhifələri `/{path}` catch-all route ilə həll olunur. Laravel ilk uyğun gələn
 * route-u seçdiyi üçün bu blok routes/web.php-nin SONUNDA olmalıdır — əks halda /login,
 * /admin/… kimi ünvanlar kateqoriya kimi oxunardı.
 *
 * Bu test yeni route əlavə edəndə səhvən catch-all-dan sonra yazılmasını tutur.
 */
class RouteRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider existingRoutes
     */
    public function test_existing_routes_are_not_swallowed_by_the_category_catch_all(string $uri, string $expectedName): void
    {
        $route = Route::getRoutes()->match(
            \Illuminate\Http\Request::create($uri, 'GET')
        );

        $this->assertSame($expectedName, $route->getName(), "\"{$uri}\" səhv route-a düşdü");
    }

    public static function existingRoutes(): array
    {
        return [
            'ana səhifə' => ['/', 'home'],
            'qaydalar' => ['/qaydalar', 'terms'],
            'giriş' => ['/login', 'login'],
            'qeydiyyat' => ['/register', 'register'],
            'parol bərpası' => ['/forgot-password', 'password.request'],
            'email təsdiqi' => ['/verify-email', 'verification.notice'],
            'parol təsdiqi' => ['/confirm-password', 'password.confirm'],
            'profil' => ['/profile', 'profile.edit'],
            'panel yoxdur' => ['/panel-yoxdur', 'no-panel'],
            'admin giriş' => ['/admin/login', 'admin.login'],
            'admin panel' => ['/admin/dashboard', 'admin.dashboard'],
            'admin kateqoriyalar' => ['/admin/categories', 'admin.categories.index'],
            'admin imtahanlar' => ['/admin/exams', 'admin.exams.index'],
            'admin imtahan yaratma' => ['/admin/exams/create', 'admin.exams.create'],
            'admin qiymətləndirmə' => ['/admin/grading', 'admin.grading.index'],
            'kataloq' => ['/imtahanlar', 'exams.catalog'],
            'ru kataloq' => ['/ru/imtahanlar', 'ru.exams.catalog'],
            'imtahan səhifəsi' => ['/imtahan/buraxilis-sinagi', 'exam.show'],
            'imtahana giriş' => ['/imtahan/buraxilis-sinagi/giris', 'exam.enter'],
            'ru imtahan səhifəsi' => ['/ru/imtahan/buraxilis-sinagi', 'ru.exam.show'],
            'şagird paneli' => ['/student/dashboard', 'student.dashboard'],
            'şagird imtahanları' => ['/student/exams', 'student.exams.index'],
            'şagird nəticələri' => ['/student/results', 'student.results.index'],
            'ru ana səhifə' => ['/ru', 'ru.home'],
            'ru qaydalar' => ['/ru/qaydalar', 'ru.terms'],
            'ru giriş' => ['/ru/login', 'ru.login'],
        ];
    }

    /** Naməlum ünvanlar kateqoriya kimi həll olunur (və kateqoriya yoxdursa 404 verir). */
    public function test_unknown_paths_fall_through_to_the_category_route(): void
    {
        $route = Route::getRoutes()->match(
            \Illuminate\Http\Request::create('/abituriyent/1-ci-qrup', 'GET')
        );

        $this->assertSame('category.show', $route->getName());
    }

    /** Rus kateqoriya ünvanı prefikssiz catch-all tərəfindən udulmamalıdır. */
    public function test_russian_category_paths_use_the_russian_route(): void
    {
        $route = Route::getRoutes()->match(
            \Illuminate\Http\Request::create('/ru/abituriyent', 'GET')
        );

        $this->assertSame('ru.category.show', $route->getName());
    }

    public function test_a_missing_category_returns_404(): void
    {
        $this->get('/bele-bir-sehife-yoxdur')->assertNotFound();
    }
}
