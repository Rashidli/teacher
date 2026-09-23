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
 * sual tipi, düzgün cavab, variant dəsti, variantların SIRASI və qəbul olunan cavablar.
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

        $keepsOptions = $question->type === Question::TYPE_MULTIPLE_CHOICE
            || in_array($question->subtype, [Question::CODED_MULTI_SELECT, Question::CODED_ORDERING], true);

        return array_merge([
            'question_text' => $question->question_text,
            'type' => $question->type,
            'subtype' => $question->subtype,
            'options' => $keepsOptions ? $options : [],
            'accepted_answers' => $question->accepted_answers ?? [],
        ], $overrides);
    }

    /**
     * Kodlaşdırılan tapşırıq (seçim/ardıcıllıq): variantlar verilən sıra ilə yaranır,
     * `order` düzgün ardıcıllığı bildirir.
     *
     * @param  array<int, string>  $texts
     * @param  array<int, int>  $correct  düzgün variantların indeksləri
     */
    private function codedQuestion(string $subtype, array $texts, array $correct = []): Question
    {
        $question = Question::create([
            'subject_id' => $this->exam->subject_id,
            'question_text' => 'Tapşırıq',
            'type' => Question::TYPE_OPEN_CODED,
            'subtype' => $subtype,
            'language' => $this->exam->sector,
        ]);

        foreach ($texts as $index => $text) {
            $question->options()->create([
                'option_letter' => chr(65 + $index),
                'option_text' => $text,
                'is_correct' => in_array($index, $correct, true),
                'order' => $index + 1,
            ]);
        }

        return $question->load('options');
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

    /**
     * Variantların YERİ dəyişdirilə bilməz.
     *
     * Düzgün cavab artıq variantların sırasından hesablanır (ardıcıllıq, uyğunluq), ona görə
     * yerdəyişmə köhnə cəhdlərin nəticəsini səssizcə dəyişər. Formada variantlar hər dəfə
     * A, B, C … kimi yenidən hərfləndiyi üçün yoxlama MƏTNLƏ aparılır.
     */
    public function test_the_option_order_can_not_be_changed(): void
    {
        $question = $this->attach(Question::factory()->withOptions(4, 'A')->create([
            'subject_id' => $this->exam->subject_id,
        ]));
        $this->useInAttempt();

        $before = $question->refresh()->options->sortBy('order')->pluck('option_text')->values()->all();

        $payload = $this->payloadFor($question);
        // A və B-nin mətnləri yerini dəyişir, hərflər isə olduğu kimi qalır
        [$payload['options'][0]['option_text'], $payload['options'][1]['option_text']]
            = [$payload['options'][1]['option_text'], $payload['options'][0]['option_text']];

        $this->update($question, $payload)->assertSessionHasErrors('options');

        $this->assertSame($before, $question->refresh()->options->sortBy('order')->pluck('option_text')->values()->all());
    }

    /** Ardıcıllıq tapşırığında sıra düzgün cavabın ÖZÜDÜR. */
    public function test_the_order_of_an_ordering_task_can_not_be_changed(): void
    {
        $question = $this->attach($this->codedQuestion(Question::CODED_ORDERING, ['1918', '1920', '1991']));
        $this->useInAttempt();

        $payload = $this->payloadFor($question, ['options' => [
            ['option_letter' => 'A', 'option_text' => '1920', 'is_correct' => false],
            ['option_letter' => 'B', 'option_text' => '1918', 'is_correct' => false],
            ['option_letter' => 'C', 'option_text' => '1991', 'is_correct' => false],
        ]]);

        $this->update($question, $payload)->assertSessionHasErrors('options');

        $this->assertSame(
            ['1918', '1920', '1991'],
            $question->refresh()->options->sortBy('order')->pluck('option_text')->values()->all(),
        );
    }

    /** Seçim tapşırığında da yerdəyişmə bloklanır. */
    public function test_the_option_order_of_a_multi_select_can_not_be_changed(): void
    {
        $question = $this->attach($this->codedQuestion(
            Question::CODED_MULTI_SELECT,
            ['Birinci', 'İkinci', 'Üçüncü'],
            [0, 2],
        ));
        $this->useInAttempt();

        $payload = $this->payloadFor($question, ['options' => [
            ['option_letter' => 'A', 'option_text' => 'İkinci', 'is_correct' => true],
            ['option_letter' => 'B', 'option_text' => 'Birinci', 'is_correct' => false],
            ['option_letter' => 'C', 'option_text' => 'Üçüncü', 'is_correct' => true],
        ]]);

        $this->update($question, $payload)->assertSessionHasErrors('options');
    }

    /** Uyğunluq cütlərinin sırası da düzgün cavabdır. */
    public function test_the_order_of_matching_pairs_can_not_be_changed(): void
    {
        $question = $this->attach(Question::create([
            'subject_id' => $this->exam->subject_id,
            'question_text' => 'Uyğunlaşdırın',
            'type' => Question::TYPE_OPEN_CODED,
            'subtype' => Question::CODED_MATCHING,
            'language' => $this->exam->sector,
            'pairs' => [
                ['left' => 'Bakı', 'right' => 'Azərbaycan'],
                ['left' => 'Ankara', 'right' => 'Türkiyə'],
            ],
        ]));
        $this->useInAttempt();

        $this->update($question, [
            'question_text' => $question->question_text,
            'type' => Question::TYPE_OPEN_CODED,
            'subtype' => Question::CODED_MATCHING,
            'pairs' => [
                ['left' => 'Ankara', 'right' => 'Türkiyə'],
                ['left' => 'Bakı', 'right' => 'Azərbaycan'],
            ],
        ])->assertSessionHasErrors('pairs');

        $this->assertSame('Bakı', $question->refresh()->pairs[0]['left']);
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
