<?php

namespace Tests\Feature\Admin;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminExamSectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->exam = Exam::factory()->create();

        config(['features.teachers' => false, 'features.exam_owner_id' => $this->admin->id]);
    }

    /** Yeni imtahan yaradılanda avtomatik bir bölmə açılır — ayrıca "bölməsiz" rejim yoxdur. */
    public function test_a_new_exam_gets_a_default_section(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.exams.store'), [
            'subject_id' => Subject::factory()->create()->id,
            'group_id' => \App\Models\Group::factory()->create()->id,
            'title' => 'Yeni imtahan',
            'duration_minutes' => 60,
            'is_free' => true,
        ])->assertSessionHasNoErrors();

        $exam = Exam::where('title', 'Yeni imtahan')->firstOrFail();

        $this->assertSame(1, $exam->sections()->count());
        $this->assertSame($exam->subject_id, $exam->sections()->value('subject_id'));
    }

    public function test_an_admin_can_add_a_section(): void
    {
        $physics = Subject::factory()->create(['name' => 'Fizika']);

        $this->actingAs($this->admin, 'admin')->post(route('admin.exams.sections.store', $this->exam), [
            'subject_id' => $physics->id,
            'question_count' => 25,
            'max_score' => 150,
        ])->assertSessionHasNoErrors();

        $section = $this->exam->sections()->where('subject_id', $physics->id)->firstOrFail();

        $this->assertSame(25, $section->question_count);
        $this->assertSame('150.00', $section->max_score);
        $this->assertSame(2, $section->order);
    }

    public function test_the_same_subject_can_not_be_added_twice(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.exams.sections.store', $this->exam), [
            'subject_id' => $this->exam->subject_id,
        ])->assertSessionHasErrors('subject_id');

        $this->assertSame(1, $this->exam->sections()->count());
    }

    public function test_the_last_section_can_not_be_deleted(): void
    {
        $section = $this->exam->sections()->firstOrFail();

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.exams.sections.destroy', [$this->exam, $section]))
            ->assertSessionHasErrors('section');

        $this->assertSame(1, $this->exam->sections()->count());
    }

    public function test_a_section_with_questions_can_not_be_deleted(): void
    {
        $physics = Subject::factory()->create();
        $section = $this->exam->sections()->create(['subject_id' => $physics->id, 'order' => 2]);

        $question = Question::factory()->create(['subject_id' => $physics->id]);
        $this->exam->questions()->attach($question->id, ['section_id' => $section->id, 'order' => 1]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.exams.sections.destroy', [$this->exam, $section]))
            ->assertSessionHasErrors('section');

        $this->assertNotNull($section->fresh());
    }

    public function test_an_empty_extra_section_can_be_deleted(): void
    {
        $section = $this->exam->sections()->create([
            'subject_id' => Subject::factory()->create()->id,
            'order' => 2,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.exams.sections.destroy', [$this->exam, $section]))
            ->assertSessionHasNoErrors();

        $this->assertNull($section->fresh());
    }

    /** Sual göstərilən bölməyə düşür. */
    public function test_a_question_is_added_to_the_chosen_section(): void
    {
        $physics = Subject::factory()->create();
        $section = $this->exam->sections()->create(['subject_id' => $physics->id, 'order' => 2]);

        $this->actingAs($this->admin, 'admin')->post(route('admin.exams.questions.store', $this->exam), [
            'question_text' => 'Fizika sualı',
            'type' => Question::TYPE_OPEN_WRITTEN,
            'section_id' => $section->id,
        ])->assertSessionHasNoErrors();

        $question = Question::firstOrFail();

        $this->assertSame($section->id, $this->exam->questions()->where('questions.id', $question->id)
            ->firstOrFail()->pivot->section_id);
        // Sual bölmənin fənninə yazılır
        $this->assertSame($physics->id, $question->subject_id);
    }

    /** Başqa imtahanın bölməsi seçilə bilməz. */
    public function test_a_section_from_another_exam_is_rejected(): void
    {
        $otherSection = Exam::factory()->create()->sections()->firstOrFail();

        $this->actingAs($this->admin, 'admin')->post(route('admin.exams.questions.store', $this->exam), [
            'question_text' => 'Sual',
            'type' => Question::TYPE_OPEN_WRITTEN,
            'section_id' => $otherSection->id,
        ])->assertSessionHasErrors('section_id');
    }
}
