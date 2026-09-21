<?php

namespace Tests\Feature\Scoring;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\Question;
use App\Models\Subject;
use App\Models\SubjectGroupScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Çoxfənli imtahan: hər bölmə öz fənninin maksimal balı ilə ayrıca hesablanır,
 * ümumi bal onların cəmidir.
 *
 * Quruluş: I qrup sınağı — Riyaziyyat (150), Fizika (150), Kimya (100) → maksimum 400.
 */
class MultiSubjectScoringTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Exam $exam;

    private Group $group;

    /** @var array<string, ExamSection> */
    private array $sections = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->group = Group::factory()->create(['stage' => Group::STAGE_SECOND]);

        $maths = Subject::factory()->create(['name' => 'Riyaziyyat']);
        $physics = Subject::factory()->create(['name' => 'Fizika']);
        $chemistry = Subject::factory()->create(['name' => 'Kimya']);

        foreach ([[$maths, 150], [$physics, 150], [$chemistry, 100]] as [$subject, $max]) {
            SubjectGroupScore::create([
                'subject_id' => $subject->id,
                'group_id' => $this->group->id,
                'max_score' => $max,
            ]);
        }

        $this->exam = Exam::factory()->published()->create([
            'subject_id' => $maths->id,
            'group_id' => $this->group->id,
            'options_per_question' => 4,
            'is_free' => true,
        ]);

        // Factory ilk bölməni (Riyaziyyat) yaradır; qalan iki fənn əlavə olunur
        $this->sections['maths'] = $this->exam->sections()->firstOrFail();
        $this->sections['physics'] = $this->exam->sections()->create(['subject_id' => $physics->id, 'order' => 2]);
        $this->sections['chemistry'] = $this->exam->sections()->create(['subject_id' => $chemistry->id, 'order' => 3]);
    }

    /** Bölməyə verilmiş sayda variantlı sual əlavə edir (düzgün cavab həmişə A). */
    private function fill(ExamSection $section, int $count): void
    {
        foreach (range(1, $count) as $index) {
            $question = Question::factory()->withOptions(4, 'A')->create([
                'subject_id' => $section->subject_id,
            ]);

            $this->exam->questions()->attach($question->id, [
                'section_id' => $section->id,
                'order' => $index,
            ]);
        }
    }

    /** @param  array<string, int>  $correctPerSection  bölmə → neçə suala düzgün cavab verilsin */
    private function take(array $correctPerSection): ExamAttempt
    {
        $this->actingAs($this->student, 'student')->post(route('student.exams.start', $this->exam));
        $attempt = ExamAttempt::latest('id')->firstOrFail();

        foreach ($this->sections as $key => $section) {
            $remaining = $correctPerSection[$key] ?? 0;

            $questions = $attempt->questions()
                ->wherePivot('section_id', $section->id)
                ->with('options')
                ->get();

            foreach ($questions as $question) {
                // Əvvəlcə düzgün (A), sonra səhv (B) cavablar
                $letter = $remaining-- > 0 ? 'A' : 'B';

                $this->actingAs($this->student, 'student')->postJson(
                    route('student.exams.save-answer', $attempt),
                    [
                        'question_id' => $question->id,
                        'selected_option_id' => $question->options->firstWhere('option_letter', $letter)->id,
                    ]
                )->assertOk();
            }
        }

        $this->actingAs($this->student, 'student')->post(route('student.exams.finish', $attempt));

        return $attempt->refresh();
    }

    public function test_each_section_is_scored_with_its_own_maximum(): void
    {
        $this->fill($this->sections['maths'], 10);
        $this->fill($this->sections['physics'], 10);
        $this->fill($this->sections['chemistry'], 10);

        // Riyaziyyat 10/10, Fizika 6/10 (4 səhv), Kimya 0/10 (10 səhv)
        $attempt = $this->take(['maths' => 10, 'physics' => 6, 'chemistry' => 0]);

        $results = $attempt->sectionResults()->get()->keyBy('subject_id');

        $maths = $results[$this->sections['maths']->subject_id];
        $physics = $results[$this->sections['physics']->subject_id];
        $chemistry = $results[$this->sections['chemistry']->subject_id];

        // NB = 100 → 150 bal
        $this->assertSame('100.00', $maths->relative_score);
        $this->assertSame('150.00', $maths->subject_score);

        // NBq = 6 − 4×0.25 = 5 → 5/10 = %50 → 75 bal
        $this->assertSame('50.00', $physics->relative_score);
        $this->assertSame('75.00', $physics->subject_score);

        // NBq = 0 − 10×0.25 = −2.5 → 0 (mənfi ola bilməz)
        $this->assertSame('0.00', $chemistry->relative_score);
        $this->assertSame('0.00', $chemistry->subject_score);
    }

    public function test_the_total_is_the_sum_of_the_section_scores(): void
    {
        $this->fill($this->sections['maths'], 10);
        $this->fill($this->sections['physics'], 10);
        $this->fill($this->sections['chemistry'], 10);

        $attempt = $this->take(['maths' => 10, 'physics' => 6, 'chemistry' => 0]);

        // 150 + 75 + 0 = 225, maksimum 150+150+100 = 400 → 225/400 = %56.3
        $this->assertSame('225.00', $attempt->total_score);
        $this->assertSame('56.30', $attempt->relative_score);

        $this->assertSame(16, $attempt->correct_answers);
        $this->assertSame(14, $attempt->wrong_answers);
    }

    /** Bölmənin öz `max_score`-u varsa, bal matrisi yox, o istifadə olunur. */
    public function test_a_section_max_score_overrides_the_group_matrix(): void
    {
        $this->sections['maths']->update(['max_score' => 50]);
        $this->fill($this->sections['maths'], 4);

        $attempt = $this->take(['maths' => 4]);

        $maths = $attempt->sectionResults()->firstOrFail();

        $this->assertSame('50.00', $maths->max_score);
        $this->assertSame('50.00', $maths->subject_score);
    }

    /** Bölmə nəticəsi hesablama anında dondurulur: bal matrisi sonra dəyişsə də köhnə nəticə eynidir. */
    public function test_the_section_result_is_frozen_against_later_matrix_changes(): void
    {
        $this->fill($this->sections['maths'], 4);
        $attempt = $this->take(['maths' => 4]);

        $before = $attempt->sectionResults()->firstOrFail()->only(['max_score', 'subject_score']);

        SubjectGroupScore::where('subject_id', $this->sections['maths']->subject_id)
            ->where('group_id', $this->group->id)
            ->update(['max_score' => 999]);

        $after = $attempt->refresh()->sectionResults()->firstOrFail()->only(['max_score', 'subject_score']);

        $this->assertSame($before, $after);
        $this->assertSame('150.00', $after['max_score']);
    }

    /** Tək-fənli imtahan da bölmə üzərində işləyir: ayrıca kod yolu yoxdur. */
    public function test_a_single_subject_exam_still_works(): void
    {
        $single = Exam::factory()->published()->create([
            'group_id' => $this->group->id,
            'options_per_question' => 4,
            'is_free' => true,
        ]);

        SubjectGroupScore::create([
            'subject_id' => $single->subject_id,
            'group_id' => $this->group->id,
            'max_score' => 100,
        ]);

        $section = $single->sections()->firstOrFail();

        foreach (range(1, 4) as $index) {
            $question = Question::factory()->withOptions(4, 'A')->create(['subject_id' => $section->subject_id]);
            $single->questions()->attach($question->id, ['section_id' => $section->id, 'order' => $index]);
        }

        $this->actingAs($this->student, 'student')->post(route('student.exams.start', $single));
        $attempt = ExamAttempt::latest('id')->firstOrFail();

        foreach ($attempt->questions as $question) {
            $this->actingAs($this->student, 'student')->postJson(
                route('student.exams.save-answer', $attempt),
                [
                    'question_id' => $question->id,
                    'selected_option_id' => $question->options->firstWhere('option_letter', 'A')->id,
                ]
            );
        }

        $this->actingAs($this->student, 'student')->post(route('student.exams.finish', $attempt));
        $attempt->refresh();

        $this->assertSame(1, $attempt->sectionResults()->count());
        $this->assertSame('100.00', $attempt->relative_score);
        $this->assertSame('100.00', $attempt->total_score);
    }
}
