<?php

namespace Tests\Feature\Student;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Group;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Şagird panelindəki dörd göstərici.
 *
 * Rəqəmlər statistika səhifəsi ilə eyni mənbədən (StudentStatistics) gəlir: nisbi bal
 * (100-lük), bir onluğa yuvarlaqlaşdırılmış. Əvvəl burada xam SQL AVG göstərilirdi
 * ("45.000000"), ən yüksək bal və düzgün cavab faizi isə heç göndərilmirdi.
 */
class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->group = Group::factory()->create();
    }

    private function attempt(float $relative, int $correct, int $wrong, int $unanswered = 0): ExamAttempt
    {
        $exam = Exam::factory()->create([
            'subject_id' => Subject::factory()->create()->id,
            'group_id' => $this->group->id,
        ]);

        return ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'group_id' => $this->group->id,
            'status' => ExamAttempt::STATUS_COMPLETED,
            'started_at' => '2026-09-01 10:00:00',
            'finished_at' => '2026-09-01 11:00:00',
            'time_spent_seconds' => 1800,
            'total_score' => $relative * 1.5,
            'relative_score' => $relative,
            'correct_answers' => $correct,
            'wrong_answers' => $wrong,
            'unanswered' => $unanswered,
        ]);
    }

    public function test_the_average_is_rounded_instead_of_a_raw_sql_average(): void
    {
        $this->attempt(45.0, 14, 11);

        $this->actingAs($this->student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/Dashboard')
                // "45.000000" deyil — tam ədəd JSON-da da tam qalır
                ->where('stats.averageScore', 45));
    }

    public function test_all_four_stats_are_filled(): void
    {
        $this->attempt(40.0, 8, 2);      // 10 sualdan 8 düz
        $this->attempt(60.0, 6, 3, 1);   // 10 sualdan 6 düz

        $this->actingAs($this->student)
            ->get(route('student.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.totalAttempts', 2)
                ->where('stats.averageScore', 50)
                ->where('stats.highestScore', 60)
                // (8 + 6) / 20 — boş buraxılan da məxrəcə düşür
                ->where('stats.correctPercentage', 70));
    }

    /** Ondalıq nəticə bir rəqəmə yuvarlaqlaşır, uzun quyruq görünmür. */
    public function test_a_repeating_average_is_shortened(): void
    {
        $this->attempt(50.0, 1, 1);
        $this->attempt(55.0, 1, 1);
        $this->attempt(61.0, 1, 1);

        $this->actingAs($this->student)
            ->get(route('student.dashboard'))
            ->assertInertia(fn ($page) => $page->where('stats.averageScore', 55.3));
    }

    /** Cəhdi olmayan şagird "0" görür, səhifə sınmır. */
    public function test_a_student_without_attempts_sees_zeroes(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.totalAttempts', 0)
                ->where('stats.averageScore', 0)
                ->where('stats.highestScore', 0)
                ->where('stats.correctPercentage', 0));
    }
}
