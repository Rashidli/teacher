<?php

namespace App\Services\Statistics;

use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Şagird statistikası: fənn üzrə irəliləyiş, mövzu üzrə zəif yerlər və cəhdlərin müqayisəsi.
 *
 * Mənbə dondurulmuş nəticələrdir (`attempt_sections`, `attempt_answers`): sual bankı sonradan
 * dəyişsə də köhnə statistika dəyişmir.
 *
 * Yalnız TAMAMLANMIŞ cəhdlər sayılır; yoxlanmamış yazılı suallar (`grade_ratio = null`)
 * nə düz, nə səhv sayılır — nəticə hazır olanda avtomatik siyahıya düşür.
 */
class StudentStatistics
{
    /** Mövzu "zəif" sayılmaq üçün ən azı bu qədər cavablanmış sual olmalıdır */
    public const MIN_TOPIC_ANSWERS = 3;

    /** Bu faizdən aşağı nəticə zəif sayılır */
    public const WEAK_THRESHOLD = 60.0;

    public function overview(User $student): array
    {
        $completed = $this->completedAttempts($student);

        return [
            'attempts' => $completed->count(),
            'exams' => $completed->pluck('exam_id')->unique()->count(),
            // Nisbi bal (100-lük) fənlər arasında müqayisə oluna bilən yeganə ölçüdür
            'average_relative' => $this->round($completed->avg('relative_score')),
            'best_relative' => $this->round($completed->max('relative_score')),
            'total_minutes' => (int) round($completed->sum('time_spent_seconds') / 60),
        ];
    }

    /**
     * Fənn üzrə irəliləyiş: hər fənn üçün cəhd sayı, orta/ən yüksək nisbi bal və
     * son iki nəticənin fərqi (müsbət = irəliləyiş).
     *
     * @return array<int, array<string, mixed>>
     */
    public function subjectProgress(User $student): array
    {
        $sections = DB::table('attempt_sections')
            ->join('exam_attempts', 'exam_attempts.id', '=', 'attempt_sections.attempt_id')
            ->join('subjects', 'subjects.id', '=', 'attempt_sections.subject_id')
            ->where('exam_attempts.user_id', $student->id)
            ->whereIn('exam_attempts.status', $this->countedStatuses())
            ->whereNotNull('attempt_sections.relative_score')
            ->orderBy('exam_attempts.finished_at')
            ->get([
                'subjects.id as subject_id',
                'subjects.name as subject',
                'attempt_sections.relative_score',
                'attempt_sections.subject_score',
                'attempt_sections.max_score',
                'exam_attempts.finished_at',
            ]);

        return collect($sections)
            ->groupBy('subject_id')
            ->map(function (Collection $rows) {
                $scores = $rows->pluck('relative_score')->map(fn ($value) => (float) $value);
                $last = $scores->last();
                $previous = $scores->count() > 1 ? $scores[$scores->count() - 2] : null;

                return [
                    'subject' => $rows->first()->subject,
                    'attempts' => $rows->count(),
                    'average' => $this->round($scores->avg()),
                    'best' => $this->round($scores->max()),
                    'last' => $this->round($last),
                    // Son cəhd əvvəlkindən neçə bal fərqlənir (null = müqayisə üçün cəhd azdır)
                    'change' => $previous === null ? null : $this->round($last - $previous),
                    'history' => $scores->map(fn ($value) => $this->round($value))->values()->all(),
                ];
            })
            ->sortByDesc('attempts')
            ->values()
            ->all();
    }

    /**
     * Mövzu üzrə zəif yerlər: ən azı MIN_TOPIC_ANSWERS cavab verilmiş mövzular
     * düzgünlük faizinə görə sıralanır.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topicBreakdown(User $student, ?int $limit = null): array
    {
        $rows = AttemptAnswer::query()
            ->join('exam_attempts', 'exam_attempts.id', '=', 'attempt_answers.attempt_id')
            ->join('questions', 'questions.id', '=', 'attempt_answers.question_id')
            ->join('topics', 'topics.id', '=', 'questions.topic_id')
            ->leftJoin('topics as parents', 'parents.id', '=', 'topics.parent_id')
            ->join('subjects', 'subjects.id', '=', 'questions.subject_id')
            ->where('exam_attempts.user_id', $student->id)
            ->whereIn('exam_attempts.status', $this->countedStatuses())
            // Yoxlanmamış yazılı cavab nə düz, nə səhv sayılır
            ->whereNotNull('attempt_answers.is_correct')
            ->groupBy('topics.id', 'topics.name', 'parents.name', 'subjects.name')
            ->orderBy('topics.name')
            ->get([
                'topics.id as topic_id',
                'topics.name as topic',
                'parents.name as topic_group',
                'subjects.name as subject',
                DB::raw('count(*) as answered'),
                DB::raw('sum(case when attempt_answers.is_correct = 1 then 1 else 0 end) as correct'),
            ]);

        $topics = collect($rows)
            ->filter(fn ($row) => (int) $row->answered >= self::MIN_TOPIC_ANSWERS)
            ->map(function ($row) {
                $answered = (int) $row->answered;
                $correct = (int) $row->correct;
                $accuracy = $answered === 0 ? 0.0 : $correct * 100 / $answered;

                return [
                    'topic' => $row->topic,
                    'topic_group' => $row->topic_group,
                    'subject' => $row->subject,
                    'answered' => $answered,
                    'correct' => $correct,
                    'accuracy' => $this->round($accuracy),
                    'is_weak' => $accuracy < self::WEAK_THRESHOLD,
                ];
            })
            ->sortBy('accuracy')
            ->values();

        return ($limit ? $topics->take($limit) : $topics)->all();
    }

    /**
     * Cəhdlərin zaman sırası (qrafik üçün): tarix və nisbi bal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function timeline(User $student, int $limit = 20): array
    {
        return $this->completedAttempts($student)
            ->sortBy('finished_at')
            ->take(-$limit)
            ->map(fn (ExamAttempt $attempt) => [
                'id' => $attempt->id,
                'exam' => $attempt->exam?->title,
                'date' => $attempt->finished_at?->format('d.m.Y'),
                'relative_score' => $this->round($attempt->relative_score),
                'total_score' => $this->round($attempt->total_score),
            ])
            ->values()
            ->all();
    }

    /**
     * Eyni imtahanın əvvəlki cəhdləri ilə müqayisə (nəticə səhifəsi üçün).
     *
     * @return array{history: array<int, array<string, mixed>>, previous: ?array<string, mixed>, change: ?float}
     */
    public function examComparison(User $student, ExamAttempt $attempt): array
    {
        $attempts = ExamAttempt::query()
            ->where('user_id', $student->id)
            ->where('exam_id', $attempt->exam_id)
            ->whereIn('status', $this->countedStatuses())
            ->orderBy('finished_at')
            ->get(['id', 'finished_at', 'relative_score', 'total_score']);

        $history = $attempts->map(fn (ExamAttempt $item) => [
            'id' => $item->id,
            'date' => $item->finished_at?->format('d.m.Y'),
            'relative_score' => $this->round($item->relative_score),
            'total_score' => $this->round($item->total_score),
            'is_current' => $item->id === $attempt->id,
        ])->values()->all();

        $index = $attempts->search(fn (ExamAttempt $item) => $item->id === $attempt->id);
        $previous = is_int($index) && $index > 0 ? $attempts[$index - 1] : null;

        return [
            'history' => $history,
            'previous' => $previous === null ? null : [
                'date' => $previous->finished_at?->format('d.m.Y'),
                'relative_score' => $this->round($previous->relative_score),
                'total_score' => $this->round($previous->total_score),
            ],
            'change' => $previous === null
                ? null
                : $this->round((float) $attempt->relative_score - (float) $previous->relative_score),
        ];
    }

    /**
     * Bu cəhdin mövzu üzrə bölgüsü (nəticə səhifəsində "hansı mövzuda itirdim").
     *
     * @return array<int, array<string, mixed>>
     */
    public function attemptTopics(ExamAttempt $attempt): array
    {
        $rows = AttemptAnswer::query()
            ->join('questions', 'questions.id', '=', 'attempt_answers.question_id')
            ->join('topics', 'topics.id', '=', 'questions.topic_id')
            ->where('attempt_answers.attempt_id', $attempt->id)
            ->whereNotNull('attempt_answers.is_correct')
            ->groupBy('topics.id', 'topics.name')
            ->get([
                'topics.name as topic',
                DB::raw('count(*) as answered'),
                DB::raw('sum(case when attempt_answers.is_correct = 1 then 1 else 0 end) as correct'),
            ]);

        return collect($rows)
            ->map(function ($row) {
                $answered = (int) $row->answered;
                $correct = (int) $row->correct;

                return [
                    'topic' => $row->topic,
                    'answered' => $answered,
                    'correct' => $correct,
                    'accuracy' => $this->round($answered === 0 ? 0 : $correct * 100 / $answered),
                ];
            })
            ->sortBy('accuracy')
            ->values()
            ->all();
    }

    /** @return Collection<int, ExamAttempt> */
    private function completedAttempts(User $student): Collection
    {
        return $student->examAttempts()
            ->with('exam:id,title')
            ->whereIn('status', $this->countedStatuses())
            ->get();
    }

    /** Yoxlama gözləyən cəhd də sayılır: balı müvəqqətidir, amma nəticə mövcuddur */
    private function countedStatuses(): array
    {
        return [ExamAttempt::STATUS_COMPLETED, ExamAttempt::STATUS_TIMED_OUT];
    }

    private function round(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }
}
