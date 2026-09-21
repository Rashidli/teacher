<?php

namespace Tests\Feature\Scoring;

use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Group;
use App\Models\Question;
use App\Models\Subject;
use App\Models\SubjectGroupScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cəhdin qiymətləndirilməsi: üç sual tipi, DİM düsturu və yazılı cavabların yoxlanması.
 *
 * İmtahan quruluşu: 2 test + 1 qısa cavab + 1 yazılı → məxrəc = 2 + 1 + 2×1 = 5.
 * Fənnin qrupdakı maksimal balı 150.
 */
class AttemptScoringTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $admin;

    private Exam $exam;

    private Question $closedOne;

    private Question $closedTwo;

    private Question $coded;

    private Question $written;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->admin = User::factory()->admin()->create();

        $subject = Subject::factory()->create();
        $group = Group::factory()->create(['stage' => Group::STAGE_SECOND]);

        SubjectGroupScore::create([
            'subject_id' => $subject->id,
            'group_id' => $group->id,
            'max_score' => 150,
        ]);

        $this->exam = Exam::factory()->published()->create([
            'subject_id' => $subject->id,
            'group_id' => $group->id,
            'options_per_question' => 4,
            'is_free' => true,
        ]);

        $this->closedOne = $this->closedQuestion(1);
        $this->closedTwo = $this->closedQuestion(2);

        $this->coded = $this->exam->questions()->create([
            'question_text' => 'Kəsri onluq şəkildə yazın',
            'type' => Question::TYPE_OPEN_CODED,
            'accepted_answers' => ['0,5'],
            'order' => 3,
        ]);

        $this->written = $this->exam->questions()->create([
            'question_text' => 'Həlli izah edin',
            'type' => Question::TYPE_OPEN_WRITTEN,
            'order' => 4,
        ]);
    }

    private function closedQuestion(int $order): Question
    {
        $question = $this->exam->questions()->create([
            'question_text' => "Test sualı {$order}",
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'order' => $order,
        ]);

        foreach (['A', 'B', 'C', 'D'] as $index => $letter) {
            $question->options()->create([
                'option_letter' => $letter,
                'option_text' => "Variant {$letter}",
                'is_correct' => $letter === 'A',
                'order' => $index + 1,
            ]);
        }

        return $question->load('options');
    }

    private function optionOf(Question $question, string $letter): int
    {
        return $question->options->firstWhere('option_letter', $letter)->id;
    }

    private function startAttempt(): ExamAttempt
    {
        $this->actingAs($this->student, 'student')
            ->post(route('student.exams.start', $this->exam));

        return ExamAttempt::latest('id')->firstOrFail();
    }

    private function answer(ExamAttempt $attempt, Question $question, array $payload): void
    {
        $this->actingAs($this->student, 'student')
            ->postJson(route('student.exams.save-answer', $attempt), $payload + [
                'question_id' => $question->id,
            ])
            ->assertOk();
    }

    private function finish(ExamAttempt $attempt): ExamAttempt
    {
        $this->actingAs($this->student, 'student')
            ->post(route('student.exams.finish', $attempt));

        return $attempt->refresh();
    }

    public function test_an_attempt_with_a_written_question_waits_for_review(): void
    {
        $attempt = $this->startAttempt();

        $this->answer($attempt, $this->closedOne, ['selected_option_id' => $this->optionOf($this->closedOne, 'A')]);
        $this->answer($attempt, $this->closedTwo, ['selected_option_id' => $this->optionOf($this->closedTwo, 'A')]);
        $this->answer($attempt, $this->coded, ['open_answer' => '0.5']);
        $this->answer($attempt, $this->written, ['open_answer' => 'Həll belədir...']);

        $attempt = $this->finish($attempt);

        // NBq = 2, NBa = 1 (yazılı hələ yoxlanmayıb) → 3 × 100 / 5 = 60
        $this->assertSame(ExamAttempt::STATUS_PENDING_REVIEW, $attempt->status);
        $this->assertSame('60.00', $attempt->relative_score);
        $this->assertSame('90.00', $attempt->total_score);
        $this->assertNull($attempt->graded_at);
    }

    public function test_grading_the_written_answer_completes_the_attempt_and_updates_the_score(): void
    {
        $attempt = $this->startAttempt();

        $this->answer($attempt, $this->closedOne, ['selected_option_id' => $this->optionOf($this->closedOne, 'A')]);
        $this->answer($attempt, $this->closedTwo, ['selected_option_id' => $this->optionOf($this->closedTwo, 'A')]);
        $this->answer($attempt, $this->coded, ['open_answer' => '0.5']);
        $this->answer($attempt, $this->written, ['open_answer' => 'Həll belədir...']);
        $attempt = $this->finish($attempt);

        $answer = AttemptAnswer::where('attempt_id', $attempt->id)
            ->where('question_id', $this->written->id)
            ->firstOrFail();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.grading.update', [$attempt, $answer]), ['grade_ratio' => 0.5])
            ->assertSessionHasNoErrors();

        $attempt->refresh();

        // NBa = 1 + 2×0.5 = 2 → (2 + 2) × 100 / 5 = 80
        $this->assertSame(ExamAttempt::STATUS_COMPLETED, $attempt->status);
        $this->assertSame('80.00', $attempt->relative_score);
        $this->assertSame('120.00', $attempt->total_score);
        $this->assertNotNull($attempt->graded_at);
        $this->assertSame($this->admin->id, $answer->refresh()->graded_by);
    }

    /** "0,5" qəbul olunubsa şagirdin "1/2" cavabı da düzgün sayılmalıdır. */
    public function test_open_coded_answers_are_checked_automatically(): void
    {
        $attempt = $this->startAttempt();

        $this->answer($attempt, $this->coded, ['open_answer' => '1/2']);
        $this->finish($attempt);

        $answer = AttemptAnswer::where('question_id', $this->coded->id)->firstOrFail();

        $this->assertTrue($answer->is_correct);
    }

    public function test_a_wrong_coded_answer_is_not_accepted(): void
    {
        $attempt = $this->startAttempt();

        $this->answer($attempt, $this->coded, ['open_answer' => '0,7']);
        $this->finish($attempt);

        $this->assertFalse(AttemptAnswer::where('question_id', $this->coded->id)->firstOrFail()->is_correct);
    }

    public function test_wrong_closed_answers_are_penalised(): void
    {
        $attempt = $this->startAttempt();

        $this->answer($attempt, $this->closedOne, ['selected_option_id' => $this->optionOf($this->closedOne, 'A')]);
        $this->answer($attempt, $this->closedTwo, ['selected_option_id' => $this->optionOf($this->closedTwo, 'B')]);
        $this->answer($attempt, $this->coded, ['open_answer' => '0,5']);

        $attempt = $this->finish($attempt);

        // NBq = 1 − 0.25 = 0.75 | NBa = 1 → 1.75 × 100 / 5 = 35
        $this->assertSame('35.00', $attempt->relative_score);
        $this->assertSame(1, $attempt->wrong_answers);
        // Yazılı sual cavablanmayıb: yoxlama gözləmir, cəhd tamamlanır
        $this->assertSame(ExamAttempt::STATUS_COMPLETED, $attempt->status);
        $this->assertSame(1, $attempt->unanswered);
    }

    public function test_save_answer_rejects_a_question_from_another_exam(): void
    {
        $attempt = $this->startAttempt();

        $otherExam = Exam::factory()->create();
        $foreign = $otherExam->questions()->create([
            'question_text' => 'Başqa imtahanın sualı',
            'type' => Question::TYPE_OPEN_WRITTEN,
            'order' => 1,
        ]);

        $this->actingAs($this->student, 'student')
            ->postJson(route('student.exams.save-answer', $attempt), [
                'question_id' => $foreign->id,
                'open_answer' => 'nəsə',
            ])
            ->assertStatus(422);

        $this->assertSame(0, AttemptAnswer::count());
    }

    public function test_save_answer_rejects_an_option_from_another_question(): void
    {
        $attempt = $this->startAttempt();

        $this->actingAs($this->student, 'student')
            ->postJson(route('student.exams.save-answer', $attempt), [
                'question_id' => $this->closedOne->id,
                // Variant ikinci sualındır
                'selected_option_id' => $this->optionOf($this->closedTwo, 'A'),
            ])
            ->assertStatus(422);

        $this->assertSame(0, AttemptAnswer::count());
    }

    /** İmtahan gedərkən hansı variantın düzgün olduğu səhifəyə göndərilməməlidir. */
    public function test_the_attempt_page_does_not_leak_the_correct_option(): void
    {
        $attempt = $this->startAttempt();

        $response = $this->actingAs($this->student, 'student')
            ->get(route('student.exams.attempt', $attempt));

        $questions = $response->viewData('page')['props']['questions'];

        foreach ($questions as $question) {
            foreach ($question['options'] as $option) {
                $this->assertArrayNotHasKey('is_correct', $option);
            }
            $this->assertArrayNotHasKey('accepted_answers', $question);
        }
    }

    public function test_the_grading_queue_only_lists_attempts_waiting_for_review(): void
    {
        $attempt = $this->startAttempt();
        $this->answer($attempt, $this->written, ['open_answer' => 'Həll']);
        $this->finish($attempt);

        $completed = $this->startAttempt();
        $this->answer($completed, $this->closedOne, ['selected_option_id' => $this->optionOf($this->closedOne, 'A')]);
        $this->finish($completed);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.grading.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Grading/Index')
                ->has('attempts.data', 1)
                ->where('attempts.data.0.id', $attempt->id));
    }

    public function test_a_student_can_not_grade_answers(): void
    {
        $attempt = $this->startAttempt();
        $this->answer($attempt, $this->written, ['open_answer' => 'Həll']);
        $this->finish($attempt);

        $answer = AttemptAnswer::where('question_id', $this->written->id)->firstOrFail();

        $this->actingAs($this->student, 'student')
            ->post(route('admin.grading.update', [$attempt, $answer]), ['grade_ratio' => 1])
            ->assertRedirect(route('admin.login'));
    }
}
