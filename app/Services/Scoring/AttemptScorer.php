<?php

namespace App\Services\Scoring;

use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\Group;
use App\Models\Question;
use App\Models\SubjectGroupScore;
use App\Support\AnswerNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Cəhdin qiymətləndirilməsi: cavablar yoxlanır, bal DİM düsturu ilə hesablanır.
 *
 * - `multiple_choice` — seçilmiş variant düzgündürmü
 * - `open_coded` — `AnswerNormalizer` ilə avtomatik (0,5 = 0.5 = 1/2)
 * - `open_written` — admin şkala ilə qiymətləndirir; qiymətləndirilməmiş cavab varsa
 *   cəhd `pending_review` olur və bal müvəqqətidir (yoxlanmamış cavablar 0 sayılır).
 */
class AttemptScorer
{
    public function __construct(private readonly ScoringStrategy $strategy)
    {
    }

    public function score(ExamAttempt $attempt): ExamAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $exam = $attempt->exam()->with(['questions.options'])->firstOrFail();

            // N+1 olmasın: bütün cavablar bir sorğu ilə
            $answers = $attempt->answers()->get()->keyBy('question_id');

            $closedTotal = $closedCorrect = $closedWrong = 0;
            $codedTotal = $codedCorrect = $writtenTotal = 0;
            $writtenRatios = [];
            $pendingReview = false;
            $answered = 0;

            /** @var array<int, array{answer: AttemptAnswer, is_correct: ?bool, raw: float}> */
            $outcomes = [];

            foreach ($exam->questions as $question) {
                $answer = $answers->get($question->id);

                match ($question->type) {
                    Question::TYPE_OPEN_CODED => $codedTotal++,
                    Question::TYPE_OPEN_WRITTEN => $writtenTotal++,
                    default => $closedTotal++,
                };

                if ($answer === null) {
                    continue;
                }

                [$isCorrect, $raw, $isAnswered, $needsReview] = $this->evaluate($question, $answer);

                $answered += $isAnswered ? 1 : 0;
                $pendingReview = $pendingReview || $needsReview;

                if ($question->type === Question::TYPE_MULTIPLE_CHOICE && $isAnswered) {
                    $isCorrect ? $closedCorrect++ : $closedWrong++;
                }

                if ($question->type === Question::TYPE_OPEN_CODED && $isCorrect) {
                    $codedCorrect++;
                }

                if ($question->type === Question::TYPE_OPEN_WRITTEN && $answer->grade_ratio !== null) {
                    $writtenRatios[] = (float) $answer->grade_ratio;
                }

                $outcomes[] = ['answer' => $answer, 'is_correct' => $isCorrect, 'raw' => $raw];
            }

            $group = $this->scoringGroup($attempt);

            $result = $this->strategy->score(new ScoringInput(
                closedTotal: $closedTotal,
                closedCorrect: $closedCorrect,
                closedWrong: $closedWrong,
                codedTotal: $codedTotal,
                codedCorrect: $codedCorrect,
                writtenTotal: $writtenTotal,
                writtenRatios: $writtenRatios,
                maxScore: $this->maxScore($exam->subject_id, $group),
                stage: $group?->stage ?? 'second_stage',
            ));

            $this->writeAnswerScores($outcomes, $result->subjectPointsPerRawPoint());

            $attempt->update([
                'status' => $pendingReview ? 'pending_review' : 'completed',
                'finished_at' => $attempt->finished_at ?? now(),
                'graded_at' => $pendingReview ? null : now(),
                'time_spent_seconds' => $attempt->time_spent_seconds > 0
                    ? $attempt->time_spent_seconds
                    : max(0, now()->diffInSeconds($attempt->started_at, absolute: true)),
                'total_score' => $result->subjectScore,
                'relative_score' => $result->relativeScore,
                'correct_answers' => $closedCorrect + $codedCorrect,
                'wrong_answers' => $closedWrong,
                'unanswered' => max(0, $exam->questions->count() - $answered),
            ]);

            return $attempt;
        });
    }

    /**
     * @return array{0: ?bool, 1: float, 2: bool, 3: bool}  [düzgündürmü, xam bal, cavablanıb, yoxlama gözləyir]
     */
    private function evaluate(Question $question, AttemptAnswer $answer): array
    {
        $weight = (float) config('scoring.open_written_weight', 2);

        if ($question->type === Question::TYPE_MULTIPLE_CHOICE) {
            if ($answer->selected_option_id === null) {
                return [null, 0.0, false, false];
            }

            $isCorrect = $question->options
                ->firstWhere('id', $answer->selected_option_id)?->is_correct === true;

            return [$isCorrect, $isCorrect ? 1.0 : 0.0, true, false];
        }

        if ($question->type === Question::TYPE_OPEN_CODED) {
            $isCorrect = AnswerNormalizer::matches(
                $answer->open_answer,
                (array) ($question->accepted_answers ?? [])
            );

            return [$isCorrect, $isCorrect ? 1.0 : 0.0, filled($answer->open_answer), false];
        }

        // open_written: qiymətləndirilməyibsə cəhd yoxlama gözləyir
        if ($answer->grade_ratio === null) {
            return [null, 0.0, filled($answer->open_answer), filled($answer->open_answer)];
        }

        $ratio = (float) $answer->grade_ratio;

        return [$ratio > 0, $ratio * $weight, true, false];
    }

    /** @param  array<int, array{answer: AttemptAnswer, is_correct: ?bool, raw: float}>  $outcomes */
    private function writeAnswerScores(array $outcomes, float $pointsPerRawPoint): void
    {
        foreach ($outcomes as $outcome) {
            $outcome['answer']->update([
                'is_correct' => $outcome['is_correct'],
                'score_earned' => round($outcome['raw'] * $pointsPerRawPoint, 2),
            ]);
        }
    }

    /** Altqrupda (I-RK, III-DT …) ballar baş qrupda saxlanılır. */
    private function scoringGroup(ExamAttempt $attempt): ?Group
    {
        $group = $attempt->exam->group;

        return $group?->parent ?? $group;
    }

    private function maxScore(int $subjectId, ?Group $group): float
    {
        if ($group === null) {
            return (float) config('scoring.default_max_score', 100);
        }

        $score = SubjectGroupScore::where('subject_id', $subjectId)
            ->where('group_id', $group->id)
            ->value('max_score');

        return (float) ($score ?? config('scoring.default_max_score', 100));
    }
}
