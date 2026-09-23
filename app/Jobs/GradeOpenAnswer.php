<?php

namespace App\Jobs;

use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Services\Grading\AiAnswerGrader;
use App\Services\Scoring\AttemptScorer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Bir açıq yazılı cavabın avtomatik qiymətləndirilməsi.
 *
 * Uğurlu olanda cavaba qiymət, əsaslandırma və token sayları yazılır (`grade_source = ai`),
 * sonra cəhdin bütün açıq cavabları qiymətlənibsə bal YENİDƏN hesablanır və status
 * `completed` olur.
 *
 * Uğursuz olanda heç nə yazılmır: cavab `pending_review` qalır və admin əl ilə qiymətləndirir.
 * Sistem sınmır — bu, işin normal nəticələrindən biridir, ona görə job "failed" sayılmır.
 *
 * ADMIN ÜSTÜNLÜYÜ: admin artıq qiymət veribsə (`grade_source = admin`) job heç nəyə toxunmur.
 */
class GradeOpenAnswer implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $answerId)
    {
        $this->onQueue((string) config('ai_grading.queue', 'ai-grading'));
    }

    public function tries(): int
    {
        return (int) config('ai_grading.tries', 3);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(AiAnswerGrader $grader, AttemptScorer $scorer): void
    {
        $answer = AttemptAnswer::with(['question', 'attempt'])->find($this->answerId);

        if ($answer === null || $answer->attempt === null) {
            return;
        }

        // Admin qabaqlayıbsa və ya cavab artıq qiymətlənibsə toxunulmur
        if ($answer->grade_ratio !== null || $answer->grade_source === AttemptAnswer::GRADE_SOURCE_ADMIN) {
            return;
        }

        $result = $grader->grade($answer);

        if (! $result->successful()) {
            // Xərc yanıbsa (sorğu getdi, cavab yararsız çıxdı) token sayı yenə yazılır
            if ($result->inputTokens > 0 || $result->outputTokens > 0) {
                $answer->forceFill([
                    'ai_model' => $result->model,
                    'ai_input_tokens' => $result->inputTokens,
                    'ai_output_tokens' => $result->outputTokens,
                    'ai_graded_at' => now(),
                ])->save();
            }

            Log::info('AI qiymətləndirmə alınmadı, əl ilə yoxlamaya qalır', [
                'answer_id' => $answer->id,
                'reason' => $result->reason,
            ]);

            return;
        }

        $answer->forceFill([
            'grade_ratio' => $result->ratio,
            'grade_source' => AttemptAnswer::GRADE_SOURCE_AI,
            'grade_comment' => $result->comment,
            'graded_at' => now(),
            'ai_model' => $result->model,
            'ai_input_tokens' => $result->inputTokens,
            'ai_output_tokens' => $result->outputTokens,
            'ai_graded_at' => now(),
        ])->save();

        $this->rescoreWhenComplete($answer->attempt, $scorer);
    }

    /**
     * Bütün açıq yazılı cavablar qiymətlənəndə bal yenidən hesablanır.
     *
     * `AttemptScorer` özü qərar verir: yoxlanmamış yazılı cavab qalmayıbsa status
     * `completed` olur, qalıbsa `pending_review` olaraq saxlanılır.
     */
    private function rescoreWhenComplete(ExamAttempt $attempt, AttemptScorer $scorer): void
    {
        $pending = $attempt->answers()
            ->whereNull('grade_ratio')
            ->whereHas('question', fn ($query) => $query->where('type', Question::TYPE_OPEN_WRITTEN))
            ->exists();

        if (! $pending) {
            $scorer->score($attempt->fresh());
        }
    }
}
