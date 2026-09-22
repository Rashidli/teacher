<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cəhdlərdə işlənmiş sualda BAL NƏTİCƏSİNƏ təsir edən dəyişikliklər backend-də bloklanır:
 * sual tipi, düzgün cavab, variant dəsti və qəbul olunan cavablar.
 *
 * Mətn, izah, mənbə, çətinlik və mövzu dəyişikliyi sərbəstdir (yalnız xəbərdarlıq göstərilir).
 */
class UsedQuestionEditingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->student = User::factory()->student()->create();
        $this->exam = Exam::factory()->published()->create([
            'is_free' => true,
            'options_per_question' => 4,
        ]);
    }

    private function attach(Question $question, int $order = 1): Question
    {
        $this->exam->questions()->attach($question->id, [
            'section_id' => $this->exam->sections()->value('id'),
            'order' => $order,
        ]);

        return $question->load('options');
    }

    private function useInAttempt(): ExamAttempt
    {
        $this->actingAs($this->student)->post(route('student.exams.start', $this->exam));
        $attempt = ExamAttempt::latest('id')->firstOrFail();
        $this->actingAs($this->student)->post(route('student.exams.finish', $attempt));

        return $attempt;
    }

    /** @return array<string, mixed> */
    private function payloadFor(Question $question, array $overrides = []): array
    {
        $options = $question->options->map(fn ($option) => [
            'option_letter' => $option->option_letter,
            'option_text' => $option->option_text,
            'is_correct' => (bool) $option->is_correct,
        ])->all();

        return array_merge([
            'question_text' => $question->question_text,
            'type' => $question->type,
            'options' => $question->type === Question::TYPE_MULTIPLE_CHOICE ? $options : [],
            'accepted_answers' => $question->accepted_answers ?? [],
        ], $overrides);
    }

    private function update(Question $question, array $payload)
    {
        return $this->actingAs($this->admin)
            ->put(route('admin.exams.questions.update', [$this->exam, $question]), $payload);
    }

    // ---- Bloklanan dəyişikliklər ----

    public function test_the_correct_answer_can_not_be_changed(): void
    {
        $question = $this->attach(Question::factory()->withOptions(4, 'A')->create([
            'subject_id' => $this->exam->subject_id,
        ]));
        $this->useInAttempt();

        $payload = $this->payloadFor($question);
        // Düzgün cavab A-dan B-yə keçirilir
        $payload['options'][0]['is_correct'] = false;
        $payload['options'][1]['is_correct'] = true;

        $this->update($question, $payload)->assertSessionHasErrors('options');

        $this->assertSame('A', $question->refresh()->options->firstWhere('is_correct', true)->option_letter);
    }

    public function test_an_option_can_not_be_removed(): void
    {
        $question = $this->attach(Question::factory()->withOptions(4)->create([
            'subject_id' => $this->exam->subject_id,
        ]));
        $this->useInAttempt();

        $payload = $this->payloadFor($question);
        array_pop($payload['options']);

        // Variant sayı imtahanınkı ilə uyğun gəlmir: validasiya onsuz da saxlamır
        $this->update($question, $payload)->assertSessionHasErrors();

        $this->assertCount(4, $question->refresh()->options);
    }

    /** Variant hərfi dəyişdirilib başqası ilə əvəzlənə bilməz. */
    public function test_the_option_set_can_not_be_replaced(): void
    {
        $question = $this->attach(Question::factory()->withOptions(4)->create([
            'subject_id' => $this->exam->subject_id,
        ]));
        $this->useInAttempt();

        $payload = $this->payloadFor($question);
        $payload['options'][3]['option_letter'] = 'E';

        $this->update($question, $payload)->assertSessionHasErrors('options');

        $this->assertSame(
            ['A', 'B', 'C', 'D'],
            $question->refresh()->options->pluck('option_letter')->all()
        );
    }

    public function test_the_question_type_can_not_be_changed(): void
    {
        $question = $this->attach(Question::factory()->withOptions(4)->create([
            'subject_id' => $this->exam->subject_id,
        ]));
        $this->useInAttempt();

        $this->update($question, $this->payloadFor($question, [
            'type' => Question::TYPE_OPEN_WRITTEN,
            'options' => [],
        ]))->assertSessionHasErrors('type');

        $this->assertSame(Question::TYPE_MULTIPLE_CHOICE, $question->refresh()->type);
    }

    public function test_the_accepted_answers_can_not_be_changed(): void
    {
        $question = $this->attach(Question::factory()->openCoded(['0,5'])->create([
            'subject_id' => $this->exam->subject_id,
        ]));
        $this->useInAttempt();

        $this->update($question, $this->payloadFor($question, [
            'accepted_answers' => ['0,7'],
        ]))->assertSessionHasErrors('accepted_answers');

        $this->assertSame(['0,5'], $question->refresh()->accepted_answers);
    }

    // ---- İcazə verilən dəyişikliklər ----

    public function test_the_wording_and_metadata_can_still_be_fixed(): void
    {
        $topic = Topic::factory()->create(['subject_id' => $this->exam->subject_id]);
        $question = $this->attach(Question::factory()->withOptions(4, 'A')->create([
            'subject_id' => $this->exam->subject_id,
            'question_text' => 'Səhv yazılmış sual',
        ]));
        $this->useInAttempt();

        $this->update($question, $this->payloadFor($question, [
            'question_text' => 'Düzəldilmiş sual',
            'explanation' => 'Yeni izah',
            'difficulty' => Question::DIFFICULTY_HARD,
            'topic_id' => $topic->id,
            'source' => 'DİM 2023',
        ]))->assertSessionHasNoErrors();

        $question->refresh();

        $this->assertSame('Düzəldilmiş sual', $question->question_text);
        $this->assertSame(Question::DIFFICULTY_HARD, $question->difficulty);
        $this->assertSame($topic->id, $question->topic_id);
        // Düzgün cavab toxunulmayıb
        $this->assertSame('A', $question->options->firstWhere('is_correct', true)->option_letter);
    }

    /** Variantın MƏTNİ düzəldilə bilər (məna dəyişmir). */
    public function test_an_option_text_can_be_fixed(): void
    {
        $question = $this->attach(Question::factory()->withOptions(4, 'A')->create([
            'subject_id' => $this->exam->subject_id,
        ]));
        $this->useInAttempt();

        $payload = $this->payloadFor($question);
        $payload['options'][1]['option_text'] = 'Düzəldilmiş variant';

        $this->update($question, $payload)->assertSessionHasNoErrors();

        $this->assertSame(
            'Düzəldilmiş variant',
            $question->refresh()->options->firstWhere('option_letter', 'B')->option_text
        );
    }

    /** Cəhddə işlənməmiş sualda hər şey sərbəst dəyişdirilə bilər. */
    public function test_an_unused_question_can_be_changed_freely(): void
    {
        $question = $this->attach(Question::factory()->withOptions(4, 'A')->create([
            'subject_id' => $this->exam->subject_id,
        ]));

        $payload = $this->payloadFor($question);
        $payload['options'][0]['is_correct'] = false;
        $payload['options'][2]['is_correct'] = true;

        $this->update($question, $payload)->assertSessionHasNoErrors();

        $this->assertSame('C', $question->refresh()->options->firstWhere('is_correct', true)->option_letter);
    }

    /** Kopya yaradılandan sonra yeni sualda hər şey dəyişdirilə bilər. */
    public function test_the_copy_can_be_changed_freely(): void
    {
        $question = $this->attach(Question::factory()->withOptions(4, 'A')->create([
            'subject_id' => $this->exam->subject_id,
        ]));
        $this->useInAttempt();

        $this->actingAs($this->admin)
            ->post(route('admin.exams.questions.duplicate', [$this->exam, $question]));

        $copy = Question::orderByDesc('id')->firstOrFail();
        $copy->load('options');

        $payload = $this->payloadFor($copy);
        $payload['options'][0]['is_correct'] = false;
        $payload['options'][1]['is_correct'] = true;

        $this->update($copy, $payload)->assertSessionHasNoErrors();

        $this->assertSame('B', $copy->refresh()->options->firstWhere('is_correct', true)->option_letter);
        // Orijinal toxunulmayıb
        $this->assertSame('A', $question->refresh()->options->firstWhere('is_correct', true)->option_letter);
    }
}
