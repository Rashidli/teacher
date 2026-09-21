<?php

namespace Tests\Feature\Admin;

use App\Models\Exam;
use App\Models\Group;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin imtahan yaratma/redaktə axını. Müəllim modulu söndürülüb (features.teachers = false),
 * ona görə imtahanın sahibi EXAM_OWNER_ID ilə göstərilən admin hesabıdır.
 */
class AdminExamTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();

        config([
            'features.teachers' => false,
            'features.exam_owner_id' => $admin->id,
        ]);

        return $admin;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'subject_id' => Subject::factory()->create()->id,
            'group_id' => Group::factory()->create()->id,
            'title' => 'Riyaziyyat sınaq imtahanı',
            'description' => 'Qısa təsvir',
            'duration_minutes' => 90,
            'is_free' => true,
            'price' => 0,
        ], $overrides);
    }

    public function test_admin_can_open_the_create_page(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.exams.create'));

        $response->assertOk();
    }

    public function test_admin_can_create_an_exam(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.exams.store'), $this->validPayload());

        $exam = Exam::firstOrFail();

        $response->assertRedirect(route('admin.exams.show', $exam));
        $this->assertSame('Riyaziyyat sınaq imtahanı', $exam->title);
        $this->assertSame(90, $exam->duration_minutes);
        $this->assertTrue($exam->created_by_admin);
        // Müəllim modulu söndürülüb: sahib EXAM_OWNER_ID
        $this->assertSame($admin->id, $exam->teacher_id);
        // Yeni imtahan qaralamadır
        $this->assertFalse($exam->is_active);
        $this->assertFalse($exam->is_published);
    }

    public function test_paid_exam_keeps_its_price(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post(
            route('admin.exams.store'),
            $this->validPayload(['is_free' => false, 'price' => 14.50])
        );

        $this->assertSame('14.50', Exam::firstOrFail()->price);
    }

    /** EXAM_OWNER_ID yoxdursa 500 yox, formada aydın mesaj görünməlidir. */
    public function test_creating_an_exam_without_a_configured_owner_shows_a_validation_error(): void
    {
        $admin = $this->admin();
        config(['features.exam_owner_id' => null]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.exams.store'), $this->validPayload());

        $response->assertSessionHasErrors('exam_owner');
        $this->assertSame(0, Exam::count());
    }

    /** EXAM_OWNER_ID var, amma belə istifadəçi yoxdursa da 500 verilməməlidir. */
    public function test_creating_an_exam_with_a_missing_owner_user_shows_a_validation_error(): void
    {
        $admin = $this->admin();
        config(['features.exam_owner_id' => 999999]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.exams.store'), $this->validPayload());

        $response->assertSessionHasErrors('exam_owner');
        $this->assertSame(0, Exam::count());
    }

    public function test_create_page_reports_whether_the_owner_is_configured(): void
    {
        $admin = $this->admin();
        config(['features.exam_owner_id' => null]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.exams.create'))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Exams/Create')
                ->where('ownerConfigured', false));
    }

    public function test_admin_can_open_the_edit_page(): void
    {
        $admin = $this->admin();
        $exam = Exam::factory()->create(['teacher_id' => $admin->id]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.exams.edit', $exam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Exams/Edit'));
    }

    public function test_admin_can_update_an_exam(): void
    {
        $admin = $this->admin();
        $exam = Exam::factory()->create(['teacher_id' => $admin->id, 'duration_minutes' => 60]);

        $response = $this->actingAs($admin, 'admin')->put(route('admin.exams.update', $exam), [
            'title' => 'Yenilənmiş başlıq',
            'description' => null,
            'duration_minutes' => 120,
            'is_free' => false,
            'price' => 9.99,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.exams.show', $exam));

        $exam->refresh();
        $this->assertSame('Yenilənmiş başlıq', $exam->title);
        $this->assertSame(120, $exam->duration_minutes);
        $this->assertFalse($exam->is_free);
        $this->assertTrue($exam->is_active);
    }

    public function test_validation_rejects_an_out_of_range_duration(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.exams.store'), $this->validPayload(['duration_minutes' => 5]))
            ->assertSessionHasErrors('duration_minutes');
    }

    public function test_guests_can_not_reach_the_admin_exam_pages(): void
    {
        $this->get(route('admin.exams.create'))->assertRedirect(route('admin.login'));
    }

    public function test_students_can_not_reach_the_admin_exam_pages(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student, 'student')
            ->get(route('admin.exams.create'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_the_exam_list_filters_by_subject(): void
    {
        $admin = $this->admin();
        $wanted = Subject::factory()->create();
        $other = Subject::factory()->create();

        Exam::factory()->create(['teacher_id' => $admin->id, 'subject_id' => $wanted->id, 'title' => 'Axtarılan']);
        Exam::factory()->create(['teacher_id' => $admin->id, 'subject_id' => $other->id, 'title' => 'Digəri']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.exams.index', ['subject_id' => $wanted->id]))
            ->assertInertia(fn ($page) => $page
                ->has('exams.data', 1)
                ->where('exams.data.0.title', 'Axtarılan')
                // Seçim səhifəyə geri qaytarılır ki, filtr formada görünsün
                ->where('filters.subject_id', (string) $wanted->id));
    }

    public function test_the_exam_list_filters_by_group(): void
    {
        $admin = $this->admin();
        $wanted = Group::factory()->create();

        Exam::factory()->create(['teacher_id' => $admin->id, 'group_id' => $wanted->id, 'title' => 'Axtarılan']);
        Exam::factory()->create(['teacher_id' => $admin->id, 'title' => 'Digəri']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.exams.index', ['group_id' => $wanted->id]))
            ->assertInertia(fn ($page) => $page->has('exams.data', 1)
                ->where('exams.data.0.title', 'Axtarılan'));
    }

    /** Səhifədəki dörd status seçiminin hamısı işləməlidir. */
    public function test_the_exam_list_filters_by_every_status_option(): void
    {
        $admin = $this->admin();

        Exam::factory()->create(['teacher_id' => $admin->id, 'title' => 'Yayımda', 'is_published' => true, 'is_active' => true]);
        Exam::factory()->create(['teacher_id' => $admin->id, 'title' => 'Qaralama', 'is_published' => false, 'is_active' => false]);

        $expectations = [
            'published' => 'Yayımda',
            'draft' => 'Qaralama',
            'active' => 'Yayımda',
            'inactive' => 'Qaralama',
        ];

        foreach ($expectations as $status => $expectedTitle) {
            $this->actingAs($admin, 'admin')
                ->get(route('admin.exams.index', ['status' => $status]))
                ->assertInertia(fn ($page) => $page
                    ->has('exams.data', 1)
                    ->where('exams.data.0.title', $expectedTitle));
        }
    }

    /** Səhifələmə keçidlərində filtrlər itməməlidir. */
    public function test_pagination_links_keep_the_filters(): void
    {
        $admin = $this->admin();
        $subject = Subject::factory()->create();

        Exam::factory()->count(20)->create(['teacher_id' => $admin->id, 'subject_id' => $subject->id]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.exams.index', ['subject_id' => $subject->id]))
            ->assertInertia(function ($page) use ($subject) {
                $next = collect($page->toArray()['props']['exams']['links'])->firstWhere('label', '2');

                $this->assertStringContainsString('subject_id='.$subject->id, $next['url']);
            });
    }
}
