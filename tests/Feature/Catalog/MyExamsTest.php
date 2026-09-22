<?php

namespace Tests\Feature\Catalog;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamAttempt;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kabinetdəki `/student/exams` artıq kataloq deyil: alınmış, davam edən və tamamlanmış
 * imtahanları göstərir. Yeni imtahan kateqoriya ağacından tapılır.
 */
class MyExamsTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->group = Group::factory()->create();
    }

    private function exam(array $attributes = []): Exam
    {
        return Exam::factory()->published()->create(array_merge([
            'group_id' => $this->group->id,
        ], $attributes));
    }

    private function attempt(Exam $exam, string $status, ?int $minutes = null): ExamAttempt
    {
        return ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'group_id' => $this->group->id,
            'status' => $status,
            'started_at' => now()->subMinutes($minutes ?? 10),
            'finished_at' => $status === ExamAttempt::STATUS_IN_PROGRESS ? null : now(),
            'time_spent_seconds' => 600,
            'total_score' => 45,
            'relative_score' => 45,
            'correct_answers' => 9,
            'wrong_answers' => 1,
            'unanswered' => 0,
        ]);
    }

    /** Köhnə kabinet ünvanı ictimai səhifəyə 301 ilə yönləndirilir. */
    public function test_the_old_cabinet_exam_url_redirects_for_good(): void
    {
        $exam = $this->exam();

        $this->actingAs($this->student)
            ->get(route('student.exams.show', $exam))
            ->assertStatus(301)
            ->assertRedirect($exam->publicUrl());
    }

    public function test_the_page_renders_the_my_exams_component(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.exams.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Student/Exams/MyExams'));
    }

    public function test_a_student_without_exams_sees_empty_lists(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.exams.index'))
            ->assertInertia(fn ($page) => $page
                ->has('inProgress', 0)
                ->has('available', 0)
                ->has('completed', 0));
    }

    public function test_an_unfinished_attempt_is_listed_first(): void
    {
        $exam = $this->exam(['title' => 'Davam edən sınaq', 'duration_minutes' => 60]);
        $attempt = $this->attempt($exam, ExamAttempt::STATUS_IN_PROGRESS, 10);

        $this->actingAs($this->student)
            ->get(route('student.exams.index'))
            ->assertInertia(fn ($page) => $page
                ->has('inProgress', 1)
                ->where('inProgress.0.title', 'Davam edən sınaq')
                ->where('inProgress.0.url', route('student.exams.attempt', $attempt))
                ->where('inProgress.0.exam_url', $exam->publicUrl()));
    }

    public function test_purchased_exams_are_listed_with_their_source(): void
    {
        $exam = $this->exam(['title' => 'Alınmış sınaq', 'is_free' => false, 'price' => 10]);

        ExamAccess::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'source' => ExamAccess::SOURCE_PAYMENT,
        ]);

        $this->actingAs($this->student)
            ->get(route('student.exams.index'))
            ->assertInertia(fn ($page) => $page
                ->has('available', 1)
                ->where('available.0.title', 'Alınmış sınaq')
                ->where('available.0.source', ExamAccess::SOURCE_PAYMENT)
                ->where('available.0.url', $exam->publicUrl()));
    }

    /** Davam edən cəhdi olan imtahan "girişi olanlar" siyahısında təkrarlanmır. */
    public function test_an_exam_with_an_open_attempt_is_not_repeated(): void
    {
        $exam = $this->exam(['is_free' => false, 'price' => 10, 'duration_minutes' => 60]);

        ExamAccess::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'source' => ExamAccess::SOURCE_PAYMENT,
        ]);

        $this->attempt($exam, ExamAttempt::STATUS_IN_PROGRESS, 5);

        $this->actingAs($this->student)
            ->get(route('student.exams.index'))
            ->assertInertia(fn ($page) => $page->has('inProgress', 1)->has('available', 0));
    }

    public function test_finished_attempts_link_to_their_result(): void
    {
        $exam = $this->exam(['title' => 'Bitmiş sınaq']);
        $attempt = $this->attempt($exam, ExamAttempt::STATUS_COMPLETED);

        $this->actingAs($this->student)
            ->get(route('student.exams.index'))
            ->assertInertia(fn ($page) => $page
                ->has('completed', 1)
                ->where('completed.0.title', 'Bitmiş sınaq')
                ->where('completed.0.url', route('student.exams.result', $attempt)));
    }

    public function test_a_guest_is_sent_to_the_login(): void
    {
        $this->get(route('student.exams.index'))->assertRedirect(route('login'));
    }
}
