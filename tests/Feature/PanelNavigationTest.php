<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Paneldən sayta qayıdış.
 *
 * Şagird panelə keçəndən sonra kataloqa qayıda bilməlidir — əks halda yeni imtahan
 * seçmək mümkün olmur. Kataloq linki üç yerdədir: panel menyusu (masaüstü + mobil),
 * "Mənim imtahanlarım" və şagird paneli.
 */
class PanelNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create();
        $this->student->assignRole('student');
    }

    /** Kataloq route-u panel şablonlarından çağırılır: adı dəyişsə testlər partlasın. */
    public function test_the_catalog_route_exists_in_both_languages(): void
    {
        $this->assertTrue(Route::has('exams.catalog'));
        $this->assertTrue(Route::has('ru.exams.catalog'));
        $this->assertSame(url('/imtahanlar'), route('exams.catalog'));
    }

    /**
     * Panel səhifələri Ziggy ilə `exams.catalog` linkini qurur, ona görə route
     * siyahısında olmalıdır — əks halda `route()` şablonda xəta verər.
     */
    public function test_panel_pages_ship_the_catalog_route_to_the_client(): void
    {
        foreach ([
            route('student.dashboard'),
            route('student.exams.index'),
            route('student.results.index'),
        ] as $url) {
            $routes = [];

            $this->actingAs($this->student)
                ->get($url)
                ->assertOk()
                ->assertInertia(function ($page) use (&$routes) {
                    $routes = $page->toArray()['props']['ziggy']['routes'] ?? [];
                });

            // Nöqtəli açar olduğu üçün `assertInertia`-nın yol sintaksisi işləmir
            $this->assertArrayHasKey('exams.catalog', $routes, "Kataloq route-u yoxdur: {$url}");
            $this->assertSame('imtahanlar', $routes['exams.catalog']['uri']);
        }
    }

    /** Kataloq şagird üçün açıqdır: paneldən keçid 404 və ya 403 vermir. */
    public function test_a_student_can_open_the_catalog_from_the_panel(): void
    {
        $this->actingAs($this->student)
            ->get(route('exams.catalog'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Exams/Index'));
    }

    /**
     * İki layout arasında keçid qırılmır: ictimai səhifədə hesab menyusu üçün lazım olan
     * `auth.user` prop-u paneldən gələn şagirddə də var.
     */
    public function test_the_public_header_knows_the_logged_in_student(): void
    {
        $this->actingAs($this->student)
            ->get(route('exams.catalog'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.id', $this->student->id)
                ->where('auth.user.roles', ['student']));
    }
}
