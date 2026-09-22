<?php

namespace Tests\Feature\Student;

use App\Models\AttemptAnswer;
use App\Models\AttemptSection;
use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamAttempt;
use App\Models\Group;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Services\Statistics\StudentStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Şagird statistikası dondurulmuş nəticələrdən qurulur: sual bankı sonradan dəyişsə də
 * köhnə rəqəmlər dəyişmir. Yoxlanmamış yazılı cavab (is_correct = null) nə düz, nə səhv sayılır.
 */
class StudentStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Subject $maths;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->maths = Subject::factory()->create(['name' => 'Riyaziyyat']);
        $this->group = Group::factory()->create();
    }

    /** Tamamlanmış cəhd + bölmə nəticəsi yaradır */
    private function attempt(
        float $relative,
        ?Exam $exam = null,
        ?Subject $subject = null,
        string $finishedAt = '2026-09-01 10:00:00',
        string $status = ExamAttempt::STATUS_COMPLETED,
    ): ExamAttempt {
        $subject ??= $this->maths;
        $exam ??= Exam::factory()->create(['subject_id' => $subject->id, 'group_id' => $this->group->id]);

        $attempt = ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'group_id' => $this->group->id,
            'status' => $status,
            'started_at' => $finishedAt,
            'finished_at' => $finishedAt,
            'time_spent_seconds' => 1800,
            'total_score' => $relative * 1.5,
            'relative_score' => $relative,
            'correct_answers' => 10,
            'wrong_answers' => 2,
            'unanswered' => 0,
        ]);

        AttemptSection::create([
            'attempt_id' => $attempt->id,
            'subject_id' => $subject->id,
            'title' => $subject->name,
            'max_score' => 150,
            'question_count' => 12,
            'correct_answers' => 10,
            'wrong_answers' => 2,
            'unanswered' => 0,
            'relative_score' => $relative,
            'subject_score' => $relative * 1.5,
            'order' => 1,
        ]);

        return $attempt;
    }

    /** Cəhdə mövzulu cavablar əlavə edir */
    private function answers(ExamAttempt $attempt, Topic $topic, int $correct, int $wrong, int $ungraded = 0): void
    {
        foreach (range(1, $correct + $wrong + $ungraded) as $index) {
            $question = Question::factory()->create([
                'subject_id' => $topic->subject_id,
                'topic_id' => $topic->id,
            ]);

            AttemptAnswer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'is_correct' => match (true) {
                    $index <= $correct => true,
                    $index <= $correct + $wrong => false,
                    default => null,
                },
                'score_earned' => $index <= $correct ? 1 : 0,
            ]);
        }
    }

    private function statistics(): StudentStatistics
    {
        return app(StudentStatistics::class);
    }

    // ---- Ümumi ----

    public function test_the_overview_counts_only_finished_attempts(): void
    {
        $this->attempt(80);
        $this->attempt(60);

        ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => Exam::factory()->create()->id,
            'group_id' => $this->group->id,
            'status' => ExamAttempt::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $overview = $this->statistics()->overview($this->student);

        $this->assertSame(2, $overview['attempts']);
        $this->assertSame(70.0, $overview['average_relative']);
        $this->assertSame(80.0, $overview['best_relative']);
        $this->assertSame(60, $overview['total_minutes']);
    }

    public function test_another_students_attempts_are_not_counted(): void
    {
        $this->attempt(90);

        $other = User::factory()->student()->create();

        $this->assertSame(0, $this->statistics()->overview($other)['attempts']);
    }

    // ---- Fənn üzrə ----

    public function test_subject_progress_shows_the_change_between_the_last_two_attempts(): void
    {
        $this->attempt(50, finishedAt: '2026-09-01 10:00:00');
        $this->attempt(65, finishedAt: '2026-09-05 10:00:00');
        $this->attempt(60, finishedAt: '2026-09-09 10:00:00');

        $progress = $this->statistics()->subjectProgress($this->student);

        $this->assertCount(1, $progress);
        $this->assertSame('Riyaziyyat', $progress[0]['subject']);
        $this->assertSame(3, $progress[0]['attempts']);
        $this->assertSame(58.3, $progress[0]['average']);
        $this->assertSame(65.0, $progress[0]['best']);
        $this->assertSame(60.0, $progress[0]['last']);
        // Son cəhd əvvəlkindən 5 bal aşağıdır
        $this->assertSame(-5.0, $progress[0]['change']);
        $this->assertSame([50.0, 65.0, 60.0], $progress[0]['history']);
    }

    public function test_a_single_attempt_has_no_change(): void
    {
        $this->attempt(70);

        $this->assertNull($this->statistics()->subjectProgress($this->student)[0]['change']);
    }

    public function test_each_subject_is_listed_separately(): void
    {
        $physics = Subject::factory()->create(['name' => 'Fizika']);

        $this->attempt(70);
        $this->attempt(40, subject: $physics);

        $subjects = collect($this->statistics()->subjectProgress($this->student))->pluck('average', 'subject');

        $this->assertSame(70.0, $subjects['Riyaziyyat']);
        $this->assertSame(40.0, $subjects['Fizika']);
    }

    // ---- Mövzular ----

    public function test_weak_topics_are_sorted_by_accuracy(): void
    {
        $attempt = $this->attempt(60);

        $weak = Topic::factory()->create(['subject_id' => $this->maths->id, 'name' => 'Kəsrlər']);
        $strong = Topic::factory()->create(['subject_id' => $this->maths->id, 'name' => 'Faizlər']);

        $this->answers($attempt, $weak, correct: 1, wrong: 4);
        $this->answers($attempt, $strong, correct: 5, wrong: 1);

        $topics = $this->statistics()->topicBreakdown($this->student);

        $this->assertSame('Kəsrlər', $topics[0]['topic']);
        $this->assertSame(20.0, $topics[0]['accuracy']);
        $this->assertTrue($topics[0]['is_weak']);

        $this->assertSame('Faizlər', $topics[1]['topic']);
        $this->assertSame(83.3, $topics[1]['accuracy']);
        $this->assertFalse($topics[1]['is_weak']);
    }

    /** Az cavablanmış mövzu statistikaya düşmür: təsadüfi nəticə "zəif yer" sayılmasın. */
    public function test_topics_with_too_few_answers_are_skipped(): void
    {
        $attempt = $this->attempt(60);
        $topic = Topic::factory()->create(['subject_id' => $this->maths->id]);

        $this->answers($attempt, $topic, correct: 0, wrong: 2);

        $this->assertSame([], $this->statistics()->topicBreakdown($this->student));
    }

    /** Yoxlanmamış yazılı cavab nə düz, nə səhv sayılır. */
    public function test_ungraded_answers_are_excluded(): void
    {
        $attempt = $this->attempt(60);
        $topic = Topic::factory()->create(['subject_id' => $this->maths->id]);

        $this->answers($attempt, $topic, correct: 3, wrong: 1, ungraded: 5);

        $topics = $this->statistics()->topicBreakdown($this->student);

        $this->assertSame(4, $topics[0]['answered']);
        $this->assertSame(75.0, $topics[0]['accuracy']);
    }

    // ---- Eyni imtahanın cəhdləri ----

    public function test_the_result_page_compares_with_the_previous_attempt(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->maths->id, 'group_id' => $this->group->id]);

        $this->attempt(50, exam: $exam, finishedAt: '2026-09-01 10:00:00');
        $current = $this->attempt(72, exam: $exam, finishedAt: '2026-09-10 10:00:00');

        $comparison = $this->statistics()->examComparison($this->student, $current);

        $this->assertSame(50.0, $comparison['previous']['relative_score']);
        $this->assertSame(22.0, $comparison['change']);
        $this->assertCount(2, $comparison['history']);
        $this->assertTrue($comparison['history'][1]['is_current']);
    }

    public function test_the_first_attempt_has_nothing_to_compare_with(): void
    {
        $attempt = $this->attempt(50);

        $comparison = $this->statistics()->examComparison($this->student, $attempt);

        $this->assertNull($comparison['previous']);
        $this->assertNull($comparison['change']);
    }

    /** Başqa imtahanın cəhdi müqayisəyə qarışmır. */
    public function test_other_exams_are_not_compared(): void
    {
        $this->attempt(90);
        $current = $this->attempt(40);

        $this->assertNull($this->statistics()->examComparison($this->student, $current)['previous']);
    }

    // ---- Səhifələr ----

    public function test_the_statistics_page_renders(): void
    {
        $attempt = $this->attempt(75);
        $topic = Topic::factory()->create(['subject_id' => $this->maths->id, 'name' => 'Kəsrlər']);
        $this->answers($attempt, $topic, correct: 1, wrong: 4);

        ExamAccess::create([
            'user_id' => $this->student->id,
            'exam_id' => $attempt->exam_id,
            'source' => ExamAccess::SOURCE_FREE,
        ]);

        $this->actingAs($this->student)
            ->get(route('student.statistics'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/Statistics')
                ->where('overview.attempts', 1)
                ->where('subjects.0.subject', 'Riyaziyyat')
                ->where('topics.0.topic', 'Kəsrlər')
                ->has('timeline', 1)
                ->has('purchases', 1)
                ->where('purchases.0.is_active', true));
    }

    public function test_a_guest_can_not_open_the_statistics(): void
    {
        $this->get(route('student.statistics'))->assertRedirect();
    }

    public function test_the_result_page_exposes_the_comparison(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->maths->id, 'group_id' => $this->group->id]);

        $this->attempt(50, exam: $exam, finishedAt: '2026-09-01 10:00:00');
        $current = $this->attempt(70, exam: $exam, finishedAt: '2026-09-10 10:00:00');

        $this->actingAs($this->student)
            ->get(route('student.exams.result', $current))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('comparison.previous.relative_score', 50)
                ->where('comparison.change', 20)
                ->has('comparison.history', 2));
    }
}
