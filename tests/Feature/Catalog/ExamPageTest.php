<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Group;
use App\Models\User;
use App\Support\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * İctimai imtahan səhifəsi `/imtahan/{slug}`.
 *
 * Kabinetdə ayrıca imtahan səhifəsi yoxdur: qonaq da, şagird də eyni səhifəni görür,
 * fərq yalnız düymənin vəziyyətindədir.
 */
class ExamPageTest extends TestCase
{
    use RefreshDatabase;

    private function exam(array $attributes = []): Exam
    {
        return Exam::factory()->published()->create(array_merge([
            'title' => 'Buraxılış sınağı',
            'group_id' => Group::factory()->create()->id,
        ], $attributes));
    }

    public function test_a_guest_can_open_the_exam_page(): void
    {
        $exam = $this->exam();

        $this->get($exam->publicUrl())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Exam/Show')
                ->where('exam.title', 'Buraxılış sınağı')
                ->where('status.state', 'guest'));
    }

    /**
     * Qonaq "Başla" basır → giriş formasına düşür → girişdən sonra HƏMİN imtahan
     * səhifəsinə qayıdır (intended URL).
     */
    public function test_a_guest_returns_to_the_exam_page_after_logging_in(): void
    {
        $exam = $this->exam();
        $student = User::factory()->student()->create();

        // "Başla" düyməsinin hədəfi
        $this->get(route('exam.enter', $exam->slug))
            ->assertRedirect(route('login'));

        $this->post('/login', [
            'email' => $student->email,
            'password' => 'password',
        ])->assertRedirect(route('exam.enter', $exam->slug));

        // Qayıdış: imtahan səhifəsi açılır
        $this->actingAs($student)
            ->get(route('exam.enter', $exam->slug))
            ->assertRedirect($exam->publicUrl());
    }

    /** Girişdən qayıdanda cəhd AVTOMATİK başlamır: taymer şagirdin təsdiqi ilə işə düşür. */
    public function test_coming_back_from_login_does_not_start_an_attempt(): void
    {
        $exam = $this->exam(['is_free' => true]);
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('exam.enter', $exam->slug));

        $this->assertSame(0, ExamAttempt::count(), 'Cəhd öz-özünə başlamamalıdır');

        // Səhifə "Başla" düyməsini göstərir
        $this->actingAs($student)
            ->get($exam->publicUrl())
            ->assertInertia(fn ($page) => $page->where('status.state', 'ready'));

        // Cəhd yalnız təsdiqdən sonra yaranır
        $this->actingAs($student)->post(route('student.exams.start', $exam));

        $this->assertSame(1, ExamAttempt::count());
    }

    public function test_a_student_without_access_sees_the_locked_state(): void
    {
        $exam = $this->exam(['is_free' => false, 'price' => 12.00]);
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get($exam->publicUrl())
            ->assertInertia(fn ($page) => $page->where('status.state', 'locked'));
    }

    public function test_an_unpublished_exam_has_no_page(): void
    {
        $this->get($this->exam(['is_published' => false])->publicUrl())->assertNotFound();
        $this->get($this->exam(['is_active' => false])->publicUrl())->assertNotFound();
    }

    /**
     * Qonaq üçün səhifə sessiyadakı sektordan ASILI DEYİL: eyni ünvan həmişə eyni cavabı
     * verməlidir (əks halda sitemap-dakı hreflang cütü sınardı). Süzgəc kataloqdadır.
     */
    public function test_the_page_is_stable_for_guests_in_both_sectors(): void
    {
        $exam = $this->exam(['sector' => Sector::RU]);

        $this->get($exam->publicUrl())->assertOk();

        $this->withSession([Sector::SESSION_KEY => Sector::AZ])
            ->get($exam->publicUrl())
            ->assertOk();
    }

    /** Daxil olmuş şagird isə yalnız öz sektorunun imtahanını aça bilər. */
    public function test_a_student_from_the_other_sector_gets_not_found(): void
    {
        $exam = $this->exam(['sector' => Sector::RU]);
        $student = User::factory()->student()->create(['sector' => Sector::AZ]);

        $this->actingAs($student)->get($exam->publicUrl())->assertNotFound();
    }

    /** Səhifə kateqoriya zəncirini göstərir (breadcrumb + JSON-LD). */
    public function test_the_page_shows_the_category_chain(): void
    {
        $parent = Category::create(['name' => 'Orta məktəb', 'slug' => 'mekteb', 'path' => 'mekteb']);
        $node = Category::create([
            'name' => '9-cu sinif buraxılış',
            'slug' => '9-cu-sinif-buraxilis',
            'path' => 'mekteb/9-cu-sinif-buraxilis',
            'parent_id' => $parent->id,
        ]);

        $exam = $this->exam(['category_id' => $node->id]);

        $this->get($exam->publicUrl())
            ->assertInertia(fn ($page) => $page
                ->where('breadcrumb.1.name', 'Orta məktəb')
                ->where('breadcrumb.2.name', '9-cu sinif buraxılış')
                ->where('breadcrumb.3.name', 'Buraxılış sınağı'));
    }

    /** Ünvan yaradılanda qurulur və başlıq düzəldiləndə DƏYİŞMİR (paylaşılmış link sınmasın). */
    public function test_the_slug_does_not_change_when_the_title_is_edited(): void
    {
        $exam = $this->exam(['title' => 'Riyaziyyat sınağı']);

        $this->assertSame('riyaziyyat-sinagi', $exam->slug);

        $exam->update(['title' => 'Riyaziyyat sınağı (yenilənmiş)']);

        $this->assertSame('riyaziyyat-sinagi', $exam->fresh()->slug);
    }

    public function test_a_repeated_title_gets_a_unique_slug(): void
    {
        $this->exam(['title' => 'Sınaq imtahanı']);
        $second = $this->exam(['title' => 'Sınaq imtahanı']);

        $this->assertSame('sinaq-imtahani-2', $second->slug);
    }
}
