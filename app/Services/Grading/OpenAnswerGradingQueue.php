<?php

namespace App\Services\Grading;

use App\Jobs\GradeOpenAnswer;
use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\Question;

/**
 * Cəhd bitəndə açıq yazılı cavabları AI qiymətləndirmə növbəsinə qoyur.
 *
 * İdempotentdir: artıq qiymətlənmiş və ya bir dəfə AI-yə göndərilmiş cavab təkrar
 * göndərilmir (`ai_graded_at`). Beləliklə bal yenidən hesablananda (məsələn admin bir
 * cavabı düzəldəndə) növbə ikinci dəfə dolmur.
 */
class OpenAnswerGradingQueue
{
    public function __construct(private readonly AiAnswerGrader $grader) {}

    /** @return int növbəyə qoyulan cavabların sayı */
    public function dispatchFor(ExamAttempt $attempt): int
    {
        if (! $this->grader->configured()) {
            return 0;
        }

        $answers = $attempt->answers()
            ->with('question')
            ->whereNull('grade_ratio')
            ->whereNull('ai_graded_at')
            ->whereHas('question', fn ($query) => $query->where('type', Question::TYPE_OPEN_WRITTEN))
            ->get()
            ->filter(fn (AttemptAnswer $answer) => $answer->question !== null
                && $this->grader->supports($answer->question));

        foreach ($answers as $answer) {
            GradeOpenAnswer::dispatch($answer->id);
        }

        return $answers->count();
    }
}
