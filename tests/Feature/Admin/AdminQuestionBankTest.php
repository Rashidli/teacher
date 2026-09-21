<?php

namespace Tests\Feature\Admin;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminQuestionBankTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Subject $maths;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->maths = Subject::factory()->create(['name' => 'Riyaziyyat']);
    }

    public function test_the_bank_lists_questions_with_their_usage(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->maths->id]);
        $question = Question::factory()->create(['subject_id' => $this->maths->id]);
        $exam->questions()->attach($question->id, [
            'section_id' => $exam->sections()->value('id'),
            'order' => 1,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.questions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/QuestionBank/Index')
                ->has('questions.data', 1)
                ->where('questions.data.0.exams_count', 1)
                ->where('questions.data.0.attempt_usage', 0));
    }

    public function test_the_bank_filters_by_subject_topic_type_and_difficulty(): void
    {
        $physics = Subject::factory()->create(['name' => 'Fizika']);
        $topic = Topic::factory()->create(['subject_id' => $this->maths->id]);

        Question::factory()->create([
            'subject_id' => $this->maths->id,
            'topic_id' => $topic->id,
            'type' => Question::TYPE_OPEN_CODED,
            'difficulty' => Question::DIFFICULTY_HARD,
            'question_text' => 'Axtarılan sual',
        ]);
        Question::factory()->create(['subject_id' => $physics->id, 'question_text' => 'Digəri']);

        $filters = [
            ['subject_id' => $this->maths->id],
            ['topic_id' => $topic->id],
            ['type' => Question::TYPE_OPEN_CODED],
            ['difficulty' => Question::DIFFICULTY_HARD],
            ['search' => 'Axtarılan'],
        ];

        foreach ($filters as $filter) {
            $this->actingAs($this->admin, 'admin')
                ->get(route('admin.questions.index', $filter))
                ->assertInertia(fn ($page) => $page
                    ->has('questions.data', 1)
                    ->where('questions.data.0.question_text', 'Axtarılan sual'));
        }
    }

    public function test_an_unused_question_can_be_deleted_from_the_bank(): void
    {
        $question = Question::factory()->create(['subject_id' => $this->maths->id]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.questions.destroy', $question))
            ->assertSessionHasNoErrors();

        $this->assertNull(Question::find($question->id));
    }

    // ---- Mövzular ----

    public function test_an_admin_can_create_and_edit_a_topic(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.topics.store'), [
            'subject_id' => $this->maths->id,
            'name' => 'Kəsrlər',
            'quarter' => 2,
            'order' => 1,
        ])->assertSessionHasNoErrors();

        $topic = Topic::firstOrFail();
        $this->assertSame('kesrler', $topic->slug);
        $this->assertSame(2, $topic->quarter);

        $this->actingAs($this->admin, 'admin')->put(route('admin.topics.update', $topic), [
            'subject_id' => $this->maths->id,
            'name' => 'Onluq kəsrlər',
            'quarter' => 3,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Onluq kəsrlər', $topic->refresh()->name);
        $this->assertSame(3, $topic->quarter);
    }

    public function test_the_quarter_must_be_between_one_and_four(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.topics.store'), [
            'subject_id' => $this->maths->id,
            'name' => 'Səhv rüb',
            'quarter' => 5,
        ])->assertSessionHasErrors('quarter');
    }

    /** Sürücülük kimi fənlərdə rüb boş qalır. */
    public function test_a_topic_without_a_quarter_is_allowed(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.topics.store'), [
            'subject_id' => $this->maths->id,
            'name' => 'Yol nişanları',
        ])->assertSessionHasNoErrors();

        $this->assertNull(Topic::firstOrFail()->quarter);
    }

    public function test_a_topic_with_questions_can_not_be_deleted(): void
    {
        $topic = Topic::factory()->create(['subject_id' => $this->maths->id]);
        Question::factory()->create(['subject_id' => $this->maths->id, 'topic_id' => $topic->id]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.topics.destroy', $topic))
            ->assertSessionHasErrors('topic');

        $this->assertNotNull($topic->fresh());
    }

    public function test_students_can_not_reach_the_bank(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student, 'student')
            ->get(route('admin.questions.index'))
            ->assertRedirect(route('admin.login'));
    }
}
