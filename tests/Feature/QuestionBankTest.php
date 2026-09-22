<?php

namespace Tests\Feature;

use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Services\QuestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Sual bankı: sual imtahana yox, fənnə aiddir və bir neçə imtahanda işlənə bilər.
 *
 * Ən vacib zəmanət: cəhd başlayanda sual siyahısı DONDURULUR — imtahandan sual ayrılsa,
 * kopyası ilə əvəzlənsə və ya yenisi əlavə olunsa belə, köhnə nəticə səhifəsi dəyişmir.
 */
class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $admin;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->admin = User::factory()->admin()->create();
        $this->exam = Exam::factory()->published()->create([
            'is_free' => true,
            'options_per_question' => 4,
        ]);
    }

    private function addQuestion(string $text, int $order): Question
    {
        $question = Question::factory()->withOptions(4)->create([
            'subject_id' => $this->exam->subject_id,
            'question_text' => $text,
        ]);

        $this->exam->questions()->attach($question->id, [
            'section_id' => $this->exam->sections()->value('id'),
            'order' => $order,
        ]);

        return $question->load('options');
    }

    private function takeExam(): ExamAttempt
    {
        $this->actingAs($this->student)->post(route('student.exams.start', $this->exam));

        $attempt = ExamAttempt::latest('id')->firstOrFail();

        foreach ($attempt->questions as $question) {
            $this->actingAs($this->student)->postJson(
                route('student.exams.save-answer', $attempt),
                [
                    'question_id' => $question->id,
                    'selected_option_id' => $question->options->firstWhere('option_letter', 'A')->id,
                ]
            )->assertOk();
        }

        $this->actingAs($this->student)->post(route('student.exams.finish', $attempt));

        return $attempt->refresh();
    }

    // ---- Bank strukturu ----

    public function test_a_question_belongs_to_a_subject_not_to_an_exam(): void
    {
        $question = $this->addQuestion('Sual', 1);

        $this->assertSame($this->exam->subject_id, $question->subject_id);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('questions', 'exam_id'));
    }

    public function test_the_same_question_can_be_used_in_two_exams(): void
    {
        $question = $this->addQuestion('Paylaşılan sual', 1);
        $other = Exam::factory()->create(['subject_id' => $this->exam->subject_id]);

        app(QuestionService::class)->attach($other, $question);

        $this->assertSame(2, $question->exams()->count());
        $this->assertSame(1, $other->questions()->count());
    }

    /** İmtahan silinəndə sualları bankda qalır. */
    public function test_deleting_an_exam_keeps_its_questions_in_the_bank(): void
    {
        $question = $this->addQuestion('Sual', 1);

        $this->actingAs($this->admin)
            ->delete(route('admin.exams.destroy', $this->exam));

        $this->assertNotNull(Question::find($question->id));
    }

    public function test_a_question_can_be_linked_to_a_topic_of_its_subject(): void
    {
        $topic = Topic::factory()->quarter(2)->create(['subject_id' => $this->exam->subject_id]);

        $this->actingAs($this->admin)->post(route('admin.exams.questions.store', $this->exam), [
            'question_text' => 'Mövzulu sual',
            'type' => Question::TYPE_OPEN_WRITTEN,
            'topic_id' => $topic->id,
            'difficulty' => Question::DIFFICULTY_HARD,
            'source' => 'DİM 2024',
        ])->assertSessionHasNoErrors();

        $question = Question::firstOrFail();

        $this->assertSame($topic->id, $question->topic_id);
        $this->assertSame(Question::DIFFICULTY_HARD, $question->difficulty);
        $this->assertSame('DİM 2024', $question->source);
    }

    /** Başqa fənnin mövzusu seçilə bilməz. */
    public function test_a_topic_from_another_subject_is_rejected(): void
    {
        $foreignTopic = Topic::factory()->create(['subject_id' => Subject::factory()->create()->id]);

        $this->actingAs($this->admin)->post(route('admin.exams.questions.store', $this->exam), [
            'question_text' => 'Sual',
            'type' => Question::TYPE_OPEN_WRITTEN,
            'topic_id' => $foreignTopic->id,
        ])->assertSessionHasErrors('topic_id');
    }

    // ---- Dondurulmuş sual siyahısı ----

    public function test_an_attempt_freezes_the_question_list(): void
    {
        $this->addQuestion('Birinci', 1);
        $this->addQuestion('İkinci', 2);

        $attempt = $this->takeExam();

        $this->assertSame(2, $attempt->questions()->count());
        $this->assertSame(2, $attempt->correct_answers);
    }

    /** Sual imtahandan ayrılandan sonra köhnə nəticə səhifəsi dəyişmir. */
    public function test_detaching_a_question_does_not_change_an_old_result(): void
    {
        $first = $this->addQuestion('Birinci', 1);
        $this->addQuestion('İkinci', 2);

        $attempt = $this->takeExam();
        $scoreBefore = $attempt->relative_score;

        $this->actingAs($this->admin)
            ->delete(route('admin.exams.questions.destroy', [$this->exam, $first]));

        // İmtahanda bir sual qaldı, amma cəhd hələ də iki sualı göstərir
        $this->assertSame(1, $this->exam->questions()->count());
        $this->assertSame(2, $attempt->refresh()->questions()->count());
        $this->assertSame($scoreBefore, $attempt->relative_score);

        $this->actingAs($this->student)
            ->get(route('student.exams.result', $attempt))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('answers', 2));
    }

    /** Sual kopya ilə əvəzlənəndən sonra da köhnə nəticə dəyişmir. */
    public function test_replacing_a_question_with_a_copy_does_not_change_an_old_result(): void
    {
        $original = $this->addQuestion('Orijinal sual', 1);
        $this->addQuestion('İkinci', 2);

        $attempt = $this->takeExam();

        $this->actingAs($this->admin)
            ->post(route('admin.exams.questions.duplicate', [$this->exam, $original]))
            ->assertSessionHasNoErrors();

        $copy = Question::where('question_text', 'Orijinal sual')->orderByDesc('id')->firstOrFail();
        $this->assertNotSame($original->id, $copy->id);

        // İmtahan indi kopyanı işlədir, cəhd isə orijinalı
        $this->assertTrue($this->exam->questions()->where('questions.id', $copy->id)->exists());
        $this->assertFalse($this->exam->questions()->where('questions.id', $original->id)->exists());
        $this->assertTrue($attempt->questions()->where('questions.id', $original->id)->exists());

        $this->actingAs($this->student)
            ->get(route('student.exams.result', $attempt))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('answers', 2)
                ->where('answers.0.question_text', 'Orijinal sual'));
    }

    /** İmtahana sonradan sual əlavə olunsa da köhnə cəhdin sual sayı dəyişmir. */
    public function test_adding_a_question_later_does_not_change_an_old_attempt(): void
    {
        $this->addQuestion('Birinci', 1);
        $attempt = $this->takeExam();

        $this->addQuestion('Sonradan əlavə', 2);

        $this->assertSame(2, $this->exam->questions()->count());
        $this->assertSame(1, $attempt->refresh()->questions()->count());
        $this->assertSame(0, $attempt->unanswered);
    }

    // ---- Silmə qadağası ----

    public function test_a_question_used_in_an_attempt_can_not_be_deleted_from_the_bank(): void
    {
        $question = $this->addQuestion('Sual', 1);
        $this->takeExam();

        $this->expectException(RuntimeException::class);

        app(QuestionService::class)->delete($question);
    }

    public function test_an_unused_question_can_be_deleted_from_the_bank(): void
    {
        $question = $this->addQuestion('İstifadə olunmayan', 1);

        app(QuestionService::class)->delete($question);

        $this->assertNull(Question::find($question->id));
    }

    /** Cəhddə işlənmiş sual imtahandan ayrıla bilər — bu, silmək deyil. */
    public function test_a_used_question_can_still_be_detached_from_the_exam(): void
    {
        $question = $this->addQuestion('Sual', 1);
        $attempt = $this->takeExam();

        app(QuestionService::class)->detach($this->exam, $question);

        $this->assertNotNull(Question::find($question->id));
        $this->assertSame(0, $this->exam->questions()->count());
        $this->assertSame(1, $attempt->questions()->count());
        $this->assertSame(1, AttemptAnswer::where('attempt_id', $attempt->id)->count());
    }
}
