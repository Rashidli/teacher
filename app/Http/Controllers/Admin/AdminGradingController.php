<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Services\Scoring\AttemptScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Yazılı (open_written) cavabların qiymətləndirmə növbəsi.
 *
 * Şagird imtahanı bitirəndə belə suallar avtomatik yoxlanmır: cəhd `pending_review` olur.
 * Admin şkala ilə qiymət verəndən sonra bal yenidən hesablanır; bütün yazılı cavablar
 * yoxlananda cəhd `completed` olur.
 */
class AdminGradingController extends Controller
{
    public function __construct(private readonly AttemptScorer $scorer)
    {
    }

    public function index(): Response
    {
        $attempts = ExamAttempt::with(['user:id,first_name,last_name,email', 'exam:id,title,subject_id', 'exam.subject:id,name'])
            ->where('status', ExamAttempt::STATUS_PENDING_REVIEW)
            ->withCount(['answers as ungraded_count' => fn ($query) => $query
                ->whereNull('grade_ratio')
                ->whereHas('question', fn ($q) => $q->where('type', Question::TYPE_OPEN_WRITTEN))])
            ->latest('finished_at')
            ->paginate(20);

        return Inertia::render('Admin/Grading/Index', [
            'attempts' => $attempts,
        ]);
    }

    public function show(ExamAttempt $attempt): Response
    {
        $attempt->load(['user:id,first_name,last_name,email', 'exam:id,title,subject_id', 'exam.subject:id,name']);

        $answers = $attempt->answers()
            ->with('question:id,question_text,explanation,type')
            ->whereHas('question', fn ($query) => $query->where('type', Question::TYPE_OPEN_WRITTEN))
            ->get()
            ->map(fn (AttemptAnswer $answer) => [
                'id' => $answer->id,
                'question_text' => $answer->question->question_text,
                'explanation' => $answer->question->explanation,
                'open_answer' => $answer->open_answer,
                'grade_ratio' => $answer->grade_ratio,
                'graded_at' => $answer->graded_at?->toDateTimeString(),
            ]);

        return Inertia::render('Admin/Grading/Show', [
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'student' => $attempt->user?->full_name,
                'exam' => $attempt->exam?->title,
                'subject' => $attempt->exam?->subject?->name,
                'relative_score' => $attempt->relative_score,
                'total_score' => $attempt->total_score,
            ],
            'answers' => $answers,
            // Şkala config-dən gəlir: 0, 1/3, 1/2, 2/3, 1
            'scale' => array_map(
                fn ($value) => ['value' => round((float) $value, 4), 'label' => $this->scaleLabel((float) $value)],
                (array) config('scoring.open_written_scale', [0, 1 / 3, 1 / 2, 2 / 3, 1])
            ),
        ]);
    }

    public function update(Request $request, ExamAttempt $attempt, AttemptAnswer $answer): RedirectResponse
    {
        abort_unless($answer->attempt_id === $attempt->id, 404);
        abort_unless($answer->question?->type === Question::TYPE_OPEN_WRITTEN, 422);

        $allowed = array_map(
            fn ($value) => (string) round((float) $value, 4),
            (array) config('scoring.open_written_scale', [])
        );

        $validated = $request->validate([
            'grade_ratio' => ['required', 'numeric', Rule::in($allowed)],
        ], [
            'grade_ratio.in' => 'Qiymət şkalada yoxdur.',
        ]);

        $answer->update([
            'grade_ratio' => $validated['grade_ratio'],
            'graded_by' => auth()->id(),
            'graded_at' => now(),
        ]);

        // Bal hər qiymətdən sonra yenidən hesablanır; hamısı yoxlananda status "completed" olur
        $this->scorer->score($attempt->fresh());

        return back()->with('success', 'Qiymət yazıldı.');
    }

    private function scaleLabel(float $value): string
    {
        return match (true) {
            abs($value - 1 / 3) < 0.001 => '1/3',
            abs($value - 1 / 2) < 0.001 => '1/2',
            abs($value - 2 / 3) < 0.001 => '2/3',
            default => (string) round($value, 2),
        };
    }
}
