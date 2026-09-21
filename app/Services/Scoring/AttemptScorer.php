<?php

namespace App\Services\Scoring;

use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\Question;
use App\Models\SubjectGroupScore;
use App\Support\AnswerNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cəhdin qiymətləndirilməsi.
 *
 * - Suallar cəhd başlayanda DONDURULMUŞ siyahıdan götürülür (`attempt_questions`): imtahandan
 *   sual ayrılsa və ya kopyası ilə əvəzlənsə belə bu cəhdin nəticəsi dəyişmir.
 * - Bal BÖLMƏ üzrə hesablanır: hər fənn üçün DİM düsturu ayrıca işləyir (öz maksimal balı ilə),
 *   ümumi bal onların cəmidir. Tək-fənli imtahanın da bir bölməsi var, ona görə ayrıca kod yolu
 *   yoxdur.
 * - Bölmə nəticəsi `attempt_sections`-a dondurulur (max_score və NB daxil), sonra bal matrisi
 *   dəyişsə də köhnə nəticə eyni qalır.
 *
 * Sual tipləri: `multiple_choice` variantla, `open_coded` AnswerNormalizer ilə avtomatik,
 * `open_written` admin şkalası ilə (yoxlanmayıbsa cəhd `pending_review` olur).
 */
class AttemptScorer
{
    public function __construct(private readonly ScoringStrategy $strategy)
    {
    }

    public function score(ExamAttempt $attempt): ExamAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $questions = $attempt->questions()->with('options')->get();
            $answers = $attempt->answers()->get()->keyBy('question_id');

            $group = $this->scoringGroup($attempt);
            $sections = ExamSection::with('subject')
                ->whereIn('id', $questions->pluck('pivot.section_id')->filter()->unique())
                ->get()
                ->keyBy('id');

            $totals = ['subject' => 0.0, 'max' => 0.0, 'correct' => 0, 'wrong' => 0, 'unanswered' => 0];
            $pendingReview = false;
            $order = 0;

            // Nəticə hər hesablamada yenidən qurulur (yazılı cavab qiymətləndiriləndən sonra da)
            $attempt->sectionResults()->delete();

            foreach ($questions->groupBy(fn (Question $question) => $question->pivot->section_id) as $sectionId => $sectionQuestions) {
                $section = $sections->get($sectionId);

                $tally = $this->tally($sectionQuestions, $answers);
                $pendingReview = $pendingReview || $tally['pending_review'];

                $maxScore = $this->maxScore($section, $attempt, $group);

                $result = $this->strategy->score(new ScoringInput(
                    closedTotal: $tally['closed_total'],
                    closedCorrect: $tally['closed_correct'],
                    closedWrong: $tally['closed_wrong'],
                    codedTotal: $tally['coded_total'],
                    codedCorrect: $tally['coded_correct'],
                    writtenTotal: $tally['written_total'],
                    writtenRatios: $tally['written_ratios'],
                    maxScore: $maxScore,
                    stage: $group?->stage ?? Group::STAGE_SECOND,
                ));

                $this->writeAnswerScores($tally['outcomes'], $result->subjectPointsPerRawPoint());

                $unanswered = max(0, $sectionQuestions->count() - $tally['answered']);

                $attempt->sectionResults()->create([
                    'section_id' => $section?->id,
                    'subject_id' => $section?->subject_id ?? $attempt->exam->subject_id,
                    'title' => $section?->displayTitle(),
                    'max_score' => $maxScore,
                    'question_count' => $sectionQuestions->count(),
                    'correct_answers' => $tally['closed_correct'] + $tally['coded_correct'],
                    'wrong_answers' => $tally['closed_wrong'],
                    'unanswered' => $unanswered,
                    'relative_score' => $result->relativeScore,
                    'subject_score' => $result->subjectScore,
                    'order' => $section?->order ?? ++$order,
                ]);

                $totals['subject'] += $result->subjectScore;
                $totals['max'] += $maxScore;
                $totals['correct'] += $tally['closed_correct'] + $tally['coded_correct'];
                $totals['wrong'] += $tally['closed_wrong'];
                $totals['unanswered'] += $unanswered;
            }

            $attempt->update([
                'status' => $pendingReview
                    ? ExamAttempt::STATUS_PENDING_REVIEW
                    : ExamAttempt::STATUS_COMPLETED,
                'finished_at' => $attempt->finished_at ?? now(),
                'graded_at' => $pendingReview ? null : now(),
                'time_spent_seconds' => $attempt->time_spent_seconds > 0
                    ? $attempt->time_spent_seconds
                    : max(0, now()->diffInSeconds($attempt->started_at, absolute: true)),
                'total_score' => round($totals['subject'], 2),
                // Ümumi nisbi bal: bölmə maksimumlarının cəmindən
                'relative_score' => $this->relative($totals['subject'], $totals['max']),
                'correct_answers' => $totals['correct'],
                'wrong_answers' => $totals['wrong'],
                'unanswered' => $totals['unanswered'],
            ]);

            return $attempt;
        });
    }

    /**
     * Bir bölmənin cavablarını sayır.
     *
     * @param  Collection<int, Question>  $questions
     * @param  Collection<int, AttemptAnswer>  $answers
     */
    private function tally(Collection $questions, Collection $answers): array
    {
        $tally = [
            'closed_total' => 0, 'closed_correct' => 0, 'closed_wrong' => 0,
            'coded_total' => 0, 'coded_correct' => 0,
            'written_total' => 0, 'written_ratios' => [],
            'answered' => 0, 'pending_review' => false, 'outcomes' => [],
        ];

        foreach ($questions as $question) {
            match ($question->type) {
                Question::TYPE_OPEN_CODED => $tally['coded_total']++,
                Question::TYPE_OPEN_WRITTEN => $tally['written_total']++,
                default => $tally['closed_total']++,
            };

            $answer = $answers->get($question->id);

            if ($answer === null) {
                continue;
            }

            [$isCorrect, $raw, $isAnswered, $needsReview] = $this->evaluate($question, $answer);

            $tally['answered'] += $isAnswered ? 1 : 0;
            $tally['pending_review'] = $tally['pending_review'] || $needsReview;

            if ($question->type === Question::TYPE_MULTIPLE_CHOICE && $isAnswered) {
                $isCorrect ? $tally['closed_correct']++ : $tally['closed_wrong']++;
            }

            if ($question->type === Question::TYPE_OPEN_CODED && $isCorrect) {
                $tally['coded_correct']++;
            }

            if ($question->type === Question::TYPE_OPEN_WRITTEN && $answer->grade_ratio !== null) {
                $tally['written_ratios'][] = (float) $answer->grade_ratio;
            }

            $tally['outcomes'][] = ['answer' => $answer, 'is_correct' => $isCorrect, 'raw' => $raw];
        }

        return $tally;
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

    private function relative(float $subjectTotal, float $maxTotal): float
    {
        if ($maxTotal <= 0) {
            return 0.0;
        }

        $step = (float) config('scoring.relative_score_step', 0.1);
        $value = $subjectTotal * 100 / $maxTotal;

        return $step > 0 ? round(round($value / $step) * $step, 4) : $value;
    }

    /** Altqrupda (I-RK, III-DT …) ballar baş qrupda saxlanılır. */
    private function scoringGroup(ExamAttempt $attempt): ?Group
    {
        $group = $attempt->exam->group;

        return $group?->parent ?? $group;
    }

    /** Bölmədə bal göstərilməyibsə qrupun bal matrisindən götürülür. */
    private function maxScore(?ExamSection $section, ExamAttempt $attempt, ?Group $group): float
    {
        if ($section?->max_score !== null) {
            return (float) $section->max_score;
        }

        $subjectId = $section?->subject_id ?? $attempt->exam->subject_id;

        if ($group === null) {
            return (float) config('scoring.default_max_score', 100);
        }

        $score = SubjectGroupScore::where('subject_id', $subjectId)
            ->where('group_id', $group->id)
            ->value('max_score');

        return (float) ($score ?? config('scoring.default_max_score', 100));
    }
}
