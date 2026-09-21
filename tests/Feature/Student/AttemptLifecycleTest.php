<?php

namespace Tests\Feature\Student;

use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cəhdin həyat dövrü: vaxtın bitməsi və başqasının cəhdinə giriş qadağası.
 */
class AttemptLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $intruder;

    private Exam $exam;

    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->intruder = User::factory()->student()->create();

        $this->exam = Exam::factory()->published()->create([
            'is_free' => true,
            'duration_minutes' => 30,
            'options_per_question' => 4,
        ]);

        $this->question = $this->exam->questions()->create([
            'question_text' => 'Test sualı',
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'order' => 1,
        ]);

        foreach (['A', 'B', 'C', 'D'] as $index => $letter) {
            $this->question->options()->create([
                'option_letter' => $letter,
                'option_text' => "Variant {$letter}",
                'is_correct' => $letter === 'A',
                'order' => $index + 1,
            ]);
        }
    }

    private function startAttempt(): ExamAttempt
    {
        $this->actingAs($this->student, 'student')->post(route('student.exams.start', $this->exam));

        return ExamAttempt::latest('id')->firstOrFail();
    }

    /** Vaxtı keçmiş cəhdi açanda avtomatik bitir və nəticəyə yönləndirilir. */
    public function test_an_expired_attempt_is_finished_when_it_is_opened(): void
    {
        $attempt = $this->startAttempt();

        // İmtahan 30 dəqiqəlikdir: cəhdi 31 dəqiqə əvvələ çəkirik
        $attempt->forceFill(['started_at' => now()->subMinutes(31)])->save();

        $this->actingAs($this->student, 'student')
            ->get(route('student.exams.attempt', $attempt))
            ->assertRedirect(route('student.exams.result', $attempt));

        $attempt->refresh();

        $this->assertSame(ExamAttempt::STATUS_COMPLETED, $attempt->status);
        $this->assertNotNull($attempt->finished_at);
        $this->assertSame(0, $attempt->remaining_time);
    }

    /** Vaxt bitəndən sonra şagird yeni cəhdə başlaya bilər, köhnəsi bağlanır. */
    public function test_starting_again_after_the_time_is_up_closes_the_old_attempt(): void
    {
        $expired = $this->startAttempt();
        $expired->forceFill(['started_at' => now()->subMinutes(31)])->save();

        $this->actingAs($this->student, 'student')
            ->post(route('student.exams.start', $this->exam));

        $this->assertSame(ExamAttempt::STATUS_COMPLETED, $expired->refresh()->status);
        $this->assertSame(2, ExamAttempt::count());
        $this->assertSame(
            ExamAttempt::STATUS_IN_PROGRESS,
            ExamAttempt::latest('id')->first()->status
        );
    }

    /** Vaxt hələ varsa yeni cəhd yaradılmır, mövcud cəhdə qaytarılır. */
    public function test_starting_while_an_attempt_is_running_returns_to_it(): void
    {
        $attempt = $this->startAttempt();

        $this->actingAs($this->student, 'student')
            ->post(route('student.exams.start', $this->exam))
            ->assertRedirect(route('student.exams.attempt', $attempt));

        $this->assertSame(1, ExamAttempt::count());
    }

    public function test_a_finished_attempt_can_not_be_finished_again(): void
    {
        $attempt = $this->startAttempt();

        $this->actingAs($this->student, 'student')->post(route('student.exams.finish', $attempt));

        $this->actingAs($this->student, 'student')
            ->post(route('student.exams.finish', $attempt))
            ->assertForbidden();
    }

    public function test_a_student_can_not_open_someone_elses_attempt(): void
    {
        $attempt = $this->startAttempt();

        $this->actingAs($this->intruder, 'student')
            ->get(route('student.exams.attempt', $attempt))
            ->assertForbidden();
    }

    public function test_a_student_can_not_save_an_answer_to_someone_elses_attempt(): void
    {
        $attempt = $this->startAttempt();

        $this->actingAs($this->intruder, 'student')
            ->postJson(route('student.exams.save-answer', $attempt), [
                'question_id' => $this->question->id,
                'selected_option_id' => $this->question->options->first()->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, AttemptAnswer::count());
    }

    public function test_a_student_can_not_finish_someone_elses_attempt(): void
    {
        $attempt = $this->startAttempt();

        $this->actingAs($this->intruder, 'student')
            ->post(route('student.exams.finish', $attempt))
            ->assertForbidden();

        $this->assertSame(ExamAttempt::STATUS_IN_PROGRESS, $attempt->refresh()->status);
    }

    public function test_a_student_can_not_see_someone_elses_result(): void
    {
        $attempt = $this->startAttempt();
        $this->actingAs($this->student, 'student')->post(route('student.exams.finish', $attempt));

        $this->actingAs($this->intruder, 'student')
            ->get(route('student.exams.result', $attempt))
            ->assertForbidden();
    }

    /** Cavab yalnız gedən imtahana yazıla bilər: bitmiş cəhdə cavab əlavə olunmur. */
    public function test_answers_can_not_be_saved_after_the_attempt_is_finished(): void
    {
        $attempt = $this->startAttempt();
        $this->actingAs($this->student, 'student')->post(route('student.exams.finish', $attempt));

        $this->actingAs($this->student, 'student')
            ->postJson(route('student.exams.save-answer', $attempt), [
                'question_id' => $this->question->id,
                'selected_option_id' => $this->question->options->first()->id,
            ])
            ->assertForbidden();
    }

    public function test_a_guest_can_not_reach_an_attempt(): void
    {
        // actingAs sonrakı sorğulara da keçir, ona görə cəhd HTTP-siz yaradılır
        $attempt = ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $this->exam->id,
            'group_id' => $this->exam->group_id,
            'status' => ExamAttempt::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $this->get(route('student.exams.attempt', $attempt))
            ->assertRedirect(route('login'));
    }
}
