<?php

namespace Tests\Feature\Teacher;

use Illuminate\Support\Env;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Müəllim modulu açıq olanda giriş, yönləndirmə və "müəllim ol" axını.
 *
 * Route-lar tətbiq qurulanda qeydiyyatdan keçdiyi üçün bayraq config() ilə deyil,
 * mühit dəyişəni ilə (tətbiq yaradılmazdan əvvəl) açılır. Env repozitoriyası prosesdə
 * bir dəfə qurulur və artıq yüklənmiş açarı .env-dən yenidən yazır — ona görə
 * Env::enablePutenv() ilə sıfırlanır (dəyişənimiz "xaricdən təyin olunmuş" sayılsın).
 */
class TeacherModuleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        putenv('FEATURE_TEACHERS=true');
        $_ENV['FEATURE_TEACHERS'] = 'true';
        $_SERVER['FEATURE_TEACHERS'] = 'true';
        Env::enablePutenv();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        putenv('FEATURE_TEACHERS');
        unset($_ENV['FEATURE_TEACHERS'], $_SERVER['FEATURE_TEACHERS']);
        Env::enablePutenv();

        parent::tearDown();
    }

    private function verifiedTeacher(): User
    {
        $teacher = User::factory()->teacher()->create();
        TeacherProfile::create(['user_id' => $teacher->id, 'is_verified' => true]);

        return $teacher;
    }

    public function test_the_module_is_enabled_for_this_test(): void
    {
        $this->assertTrue(config('features.teachers'));
    }

    /** Vahid /login müəllimi də öz panelinə göndərir. */
    public function test_a_teacher_lands_in_the_teacher_dashboard(): void
    {
        $teacher = $this->verifiedTeacher();

        $this->post('/login', [
            'email' => $teacher->email,
            'password' => 'password',
        ])->assertRedirect(route('teacher.dashboard'));

        $this->actingAs($teacher)->get(route('teacher.dashboard'))->assertOk();
    }

    /** Ayrıca müəllim giriş səhifəsi də eyni sessiya ilə işləyir. */
    public function test_the_teacher_login_page_works_with_the_shared_guard(): void
    {
        $teacher = $this->verifiedTeacher();

        $this->post(route('teacher.login'), [
            'email' => $teacher->email,
            'password' => 'password',
        ])->assertRedirect(route('teacher.dashboard'));

        $this->assertAuthenticatedAs($teacher);
    }

    public function test_the_teacher_login_rejects_a_non_teacher_account(): void
    {
        $student = User::factory()->student()->create();

        $this->post(route('teacher.login'), [
            'email' => $student->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** Təsdiqlənməmiş müəllim gözləmə səhifəsinə düşür. */
    public function test_an_unverified_teacher_waits_for_verification(): void
    {
        $teacher = User::factory()->teacher()->create();
        TeacherProfile::create(['user_id' => $teacher->id, 'is_verified' => false]);

        $this->post('/login', [
            'email' => $teacher->email,
            'password' => 'password',
        ])->assertRedirect(route('teacher.awaiting-verification'));

        $this->actingAs($teacher)->get(route('teacher.dashboard'))
            ->assertRedirect(route('teacher.awaiting-verification'));
    }

    public function test_a_student_can_not_open_the_teacher_panel(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('teacher.dashboard'))->assertForbidden();
    }

    /** Müəllim qeydiyyatı yeni hesab yaratmır: mövcud hesaba rol və profil əlavə edir. */
    public function test_becoming_a_teacher_adds_the_role_to_the_current_account(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();

        $usersBefore = User::count();

        $this->actingAs($student)
            ->post(route('teacher.register'), ['subjects' => [$subject->id]])
            ->assertRedirect(route('teacher.dashboard'));

        $student->refresh();

        $this->assertSame($usersBefore, User::count());
        $this->assertTrue($student->hasRole('teacher'));
        // Şagird rolu itmir: hesab hər iki panelə sahib olur
        $this->assertTrue($student->hasRole('student'));
        $this->assertTrue($student->teacherProfile->is_verified);
        $this->assertTrue($student->subjects->contains($subject));
    }

    public function test_becoming_a_teacher_requires_a_login(): void
    {
        $subject = Subject::factory()->create();

        $this->post(route('teacher.register'), ['subjects' => [$subject->id]])
            ->assertRedirect(route('teacher.login'));
    }
}
