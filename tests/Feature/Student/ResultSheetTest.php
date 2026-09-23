<?php

namespace Tests\Feature\Student;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nəticə səhifəsindəki "imtahan vərəqi".
 *
 * Vərəq cavab kartını qurmaq üçün əlavə məlumat tələb edir: variant HƏRFİ (A/B/C),
 * sualın XAM DƏYƏRİ (qapalı 1, yazılı 2 bal) və başlıq üçün şagirdin adı.
 */
class ResultSheetTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private ExamAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        $subject = Subject::create(['name' => 'Riyaziyyat', 'slug' => 'riyaziyyat', 'category' => 'technical']);

        $this->student = User::factory()->create(['first_name' => 'Aysel', 'last_name' => 'Məmmədova']);
        $this->student->assignRole('student');

        $exam = Exam::factory()->published()->create([
            'subject_id' => $subject->id,
            'group_id' => Group::factory()->create()->id,
        ]);

        $section = ExamSection::firstOrCreate(
            ['exam_id' => $exam->id, 'subject_id' => $subject->id],
            ['order' => 1, 'max_score' => 100],
        );

        $closed = Question::create([
            'subject_id' => $subject->id,
            'question_text' => 'Qapalı sual',
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'language' => 'az',
        ]);

        foreach (['A' => true, 'B' => false] as $letter => $correct) {
            QuestionOption::create([
                'question_id' => $closed->id,
                'option_letter' => $letter,
                'option_text' => "Variant {$letter}",
                'is_correct' => $correct,
                'order' => $correct ? 1 : 2,
            ]);
        }

        $written = Question::create([
            'subject_id' => $subject->id,
            'question_text' => 'Yazılı sual',
            'type' => Question::TYPE_OPEN_WRITTEN,
            'language' => 'az',
        ]);

        foreach ([$closed, $written] as $order => $question) {
            $exam->questions()->attach($question->id, ['section_id' => $section->id, 'order' => $order + 1]);
        }

        $this->attempt = ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'group_id' => $exam->group_id,
            'status' => ExamAttempt::STATUS_PENDING_REVIEW,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now(),
            'time_spent_seconds' => 1200,
        ]);

        foreach ([$closed, $written] as $order => $question) {
            $this->attempt->questions()->attach($question->id, [
                'section_id' => $section->id,
                'order' => $order + 1,
            ]);
        }

        // Qapalı suala DOĞRU cavab, yazılı sual isə yoxlanmamış
        $this->attempt->answers()->create([
            'question_id' => $closed->id,
            'selected_option_id' => $closed->options()->where('is_correct', true)->value('id'),
            'is_correct' => true,
        ]);

        $this->attempt->answers()->create([
            'question_id' => $written->id,
            'open_answer' => 'Həll burada.',
        ]);
    }

    /** @return array<string, mixed> */
    private function props(): array
    {
        $props = [];

        $this->actingAs($this->student)
            ->get(route('student.exams.result', $this->attempt))
            ->assertOk()
            ->assertInertia(function ($page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        return $props;
    }

    public function test_the_sheet_header_carries_the_student_and_the_section(): void
    {
        $props = $this->props();

        $this->assertSame('Aysel Məmmədova', $props['attempt']['student']);
        $this->assertSame(20, $props['attempt']['minutes_spent']);
        $this->assertArrayHasKey('trail', $props['attempt']);
        // Yazılı cavab yoxlanmayıb: vərəqdə "ilkin" nişanı bundan asılıdır
        $this->assertTrue($props['attempt']['awaiting_review']);
    }

    /** Cavab kartı variant HƏRFİ ilə qurulur, id ilə yox. */
    public function test_every_option_ships_its_letter(): void
    {
        $closed = collect($this->props()['answers'])
            ->firstWhere('type', Question::TYPE_MULTIPLE_CHOICE);

        $letters = collect($closed['options'])->pluck('option_letter')->all();

        $this->assertSame(['A', 'B'], $letters);
        $this->assertNotNull($closed['correct_option_id']);
        $this->assertNotNull($closed['selected_option_id']);
    }

    /**
     * Sualın xam dəyəri: qapalı 1 bal, yazılı isə `scoring.open_written_weight`.
     * Tiplərin çəkisi fərqli olduğu üçün vərəqdə göstərilir.
     */
    public function test_each_question_ships_its_weight(): void
    {
        $answers = collect($this->props()['answers']);

        // JSON-da 1.0 tam ədəd kimi gəlir, ona görə dəqiq tip müqayisəsi edilmir
        $this->assertEqualsWithDelta(1, $answers->firstWhere('type', Question::TYPE_MULTIPLE_CHOICE)['weight'], 0.001);
        $this->assertEqualsWithDelta(
            (float) config('scoring.open_written_weight'),
            $answers->firstWhere('type', Question::TYPE_OPEN_WRITTEN)['weight'],
            0.001,
        );
    }
}
