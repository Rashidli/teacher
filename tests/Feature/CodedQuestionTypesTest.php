<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\Passage;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use App\Services\Scoring\AttemptScorer;
use App\Support\CodedAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DİM-in KODLAŞDIRILAN açıq tapşırıqları: seçim, ardıcıllıq, uyğunluq.
 *
 * Hamısı avtomatik yoxlanılır və qapalı sual kimi 1 xam bal dəyərindədir. Düzgün cavab
 * SAXLANILMIR, variantlardan/cütlərdən hesablanır (`App\Support\CodedAnswer`) — admin
 * variantı düzəldəndə "düzgün cavab" köhnəlməsin.
 */
class CodedQuestionTypesTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::create([
            'name' => 'Tarix', 'slug' => 'tarix', 'category' => 'humanitarian',
        ]);
    }

    /** @param  array<int, array{text: string, correct?: bool}>  $options */
    private function question(string $subtype, array $options = [], array $pairs = []): Question
    {
        $question = Question::create([
            'subject_id' => $this->subject->id,
            'question_text' => 'Tapşırıq',
            'type' => Question::TYPE_OPEN_CODED,
            'subtype' => $subtype,
            'language' => 'az',
            'pairs' => $pairs ?: null,
        ]);

        foreach ($options as $index => $option) {
            QuestionOption::create([
                'question_id' => $question->id,
                'option_letter' => chr(65 + $index),
                'option_text' => $option['text'],
                'is_correct' => $option['correct'] ?? false,
                'order' => $index + 1,
            ]);
        }

        return $question->load('options');
    }

    /* ----------------------------------------------------------- düzgün cavab */

    public function test_the_correct_answer_of_a_multi_select_comes_from_the_options(): void
    {
        $question = $this->question(Question::CODED_MULTI_SELECT, [
            ['text' => 'Birinci', 'correct' => true],
            ['text' => 'İkinci'],
            ['text' => 'Üçüncü', 'correct' => true],
        ]);

        $this->assertSame('A,C', CodedAnswer::correct($question));

        // Sıra əhəmiyyətsizdir: "C,A" da, boşluqlu və kiçik hərfli yazılış da qəbul olunur
        $this->assertTrue(CodedAnswer::matches($question, 'A,C'));
        $this->assertTrue(CodedAnswer::matches($question, 'c, a'));
        $this->assertFalse(CodedAnswer::matches($question, 'A'));
        $this->assertFalse(CodedAnswer::matches($question, 'A,B,C'));
        $this->assertFalse(CodedAnswer::matches($question, null));
    }

    /** Ardıcıllıqda düzgün cavab variantların `order` sırasıdır. */
    public function test_the_correct_answer_of_an_ordering_is_the_option_order(): void
    {
        $question = $this->question(Question::CODED_ORDERING, [
            ['text' => '1918'],
            ['text' => '1920'],
            ['text' => '1991'],
        ]);

        $this->assertSame('A,B,C', CodedAnswer::correct($question));

        $this->assertTrue(CodedAnswer::matches($question, 'A,B,C'));
        // Sıra burada ƏHƏMİYYƏTLİDİR
        $this->assertFalse(CodedAnswer::matches($question, 'C,B,A'));
    }

    /** Uyğunluqda cütlər sıralı saxlanılır: düzgün cavab "1-1,2-2,3-3"-dür. */
    public function test_the_correct_answer_of_a_matching_is_the_pair_order(): void
    {
        $question = $this->question(Question::CODED_MATCHING, [], [
            ['left' => 'Bakı', 'right' => 'Azərbaycan'],
            ['left' => 'Ankara', 'right' => 'Türkiyə'],
        ]);

        $this->assertSame('1-1,2-2', CodedAnswer::correct($question));

        $this->assertTrue(CodedAnswer::matches($question, '1-1,2-2'));
        $this->assertFalse(CodedAnswer::matches($question, '1-2,2-1'));

        // Nəticə səhifəsində oxunaqlı yazılış
        $this->assertSame('1 → 1, 2 → 2', CodedAnswer::describe($question, '1-1,2-2'));
    }

    /** Alt növü olmayan köhnə sual HESABLAMA sayılır — davranış dəyişmir. */
    public function test_a_question_without_a_subtype_is_still_a_calculation(): void
    {
        $question = Question::create([
            'subject_id' => $this->subject->id,
            'question_text' => 'Neçədir?',
            'type' => Question::TYPE_OPEN_CODED,
            'language' => 'az',
            'accepted_answers' => ['0,5'],
        ]);

        $this->assertSame(Question::CODED_NUMERIC, $question->codedSubtype());
        $this->assertTrue(CodedAnswer::matches($question, '1/2'));
        $this->assertFalse(CodedAnswer::matches($question, '0,6'));
    }

    /* ------------------------------------------------------------ bal hesabı */

    /** Hər alt növ avtomatik yoxlanılır və 1 xam bal dəyərindədir (qapalı sual kimi). */
    public function test_every_coded_subtype_is_graded_automatically_and_worth_one_point(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');

        $exam = Exam::factory()->published()->create([
            'subject_id' => $this->subject->id,
            'group_id' => Group::factory()->create()->id,
        ]);

        $section = ExamSection::firstOrCreate(
            ['exam_id' => $exam->id, 'subject_id' => $this->subject->id],
            ['order' => 1, 'max_score' => 100],
        );

        $select = $this->question(Question::CODED_MULTI_SELECT, [
            ['text' => 'a', 'correct' => true], ['text' => 'b'], ['text' => 'c', 'correct' => true],
        ]);
        $order = $this->question(Question::CODED_ORDERING, [
            ['text' => 'a'], ['text' => 'b'],
        ]);
        $match = $this->question(Question::CODED_MATCHING, [], [
            ['left' => 'a', 'right' => '1'], ['left' => 'b', 'right' => '2'],
        ]);

        $attempt = ExamAttempt::create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'group_id' => $exam->group_id,
            'status' => ExamAttempt::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(10),
        ]);

        foreach ([$select, $order, $match] as $index => $question) {
            $attempt->questions()->attach($question->id, [
                'section_id' => $section->id,
                'order' => $index + 1,
            ]);
        }

        // İki düzgün, biri səhv
        $attempt->answers()->create(['question_id' => $select->id, 'open_answer' => 'C,A']);
        $attempt->answers()->create(['question_id' => $order->id, 'open_answer' => 'A,B']);
        $attempt->answers()->create(['question_id' => $match->id, 'open_answer' => '1-2,2-1']);

        app(AttemptScorer::class)->score($attempt);

        // Yazılı sual olmadığı üçün cəhd dərhal tamamlanır — əl ilə yoxlama gözlənilmir
        $this->assertSame(ExamAttempt::STATUS_COMPLETED, $attempt->refresh()->status);
        $this->assertSame(2, $attempt->correct_answers);

        $answers = $attempt->answers()->get()->keyBy('question_id');
        $this->assertTrue($answers[$select->id]->is_correct);
        $this->assertTrue($answers[$order->id]->is_correct);
        $this->assertFalse($answers[$match->id]->is_correct);

        // Üç sualın hər biri 1 xam bal: düz cavablar bölmənin 2/3-nü verir
        $this->assertEqualsWithDelta(66.7, (float) $attempt->relative_score, 0.1);
    }

    /* ------------------------------------------------------- yazılı alt növlər */

    /** Yazılı alt növ bal qaydasını dəyişmir — yalnız məlumat üçündür. */
    public function test_a_written_subtype_does_not_change_the_weight(): void
    {
        $question = Question::create([
            'subject_id' => $this->subject->id,
            'question_text' => 'İsbat edin',
            'type' => Question::TYPE_OPEN_WRITTEN,
            'subtype' => Question::WRITTEN_PROOF,
            'language' => 'az',
        ]);

        $this->assertNull($question->codedSubtype());
        $this->assertFalse($question->isAutoGraded());
        $this->assertContains(Question::WRITTEN_PROOF, Question::subtypesFor(Question::TYPE_OPEN_WRITTEN));
        $this->assertNotContains(Question::WRITTEN_PROOF, Question::subtypesFor(Question::TYPE_OPEN_CODED));
    }

    /** Bir mətnə bir neçə sual bağlana bilər (DİM: mətn və mənbə əsaslı tapşırıqlar). */
    public function test_one_passage_can_carry_several_questions(): void
    {
        $passage = Passage::create([
            'language' => 'az',
            'title' => 'Mənbə: XIX əsr',
            'body' => 'Sənədin mətni.',
            'source' => 'Dərslik',
        ]);

        foreach ([Question::WRITTEN_TEXT, Question::WRITTEN_SOURCE] as $subtype) {
            Question::create([
                'subject_id' => $this->subject->id,
                'passage_id' => $passage->id,
                'question_text' => 'Sual: '.$subtype,
                'type' => Question::TYPE_OPEN_WRITTEN,
                'subtype' => $subtype,
                'language' => 'az',
            ]);
        }

        $this->assertSame(2, $passage->questions()->count());
        $this->assertSame('Mənbə: XIX əsr', Question::first()->passage->title);
    }
}
