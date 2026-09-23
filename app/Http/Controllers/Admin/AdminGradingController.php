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
 * Şagird imtahanı bitirəndə belə suallar `AiAnswerGrader` ilə avtomatik qiymətləndirilə
 * bilər (`grade_source = ai`). Avtomatik qiymət SON DEYİL: admin burada onu görür və
 * dəyişə bilər — adminin qiyməti həmişə üstələyir (`grade_source = admin`).
 *
 * Növbədə iki şey var: heç yoxlanmamış cavablar (cəhd `pending_review`) və şagirdin
 * ETİRAZ etdiyi avtomatik qiymətlər (`review_requested_at`).
 */
class AdminGradingController extends Controller
{
    public function __construct(private readonly AttemptScorer $scorer)
    {
    }

    public function index(): Response
    {
        $written = fn ($query) => $query->whereHas(
            'question',
            fn ($q) => $q->where('type', Question::TYPE_OPEN_WRITTEN),
        );

        $attempts = ExamAttempt::with(['user:id,first_name,last_name,email', 'exam:id,title,subject_id', 'exam.subject:id,name'])
            // Yoxlanmamış cavablar VƏ YA şagirdin etiraz etdiyi avtomatik qiymətlər
            ->where(fn ($query) => $query
                ->where('status', ExamAttempt::STATUS_PENDING_REVIEW)
                ->orWhereHas('answers', fn ($answers) => $answers->whereNotNull('review_requested_at')))
            ->withCount([
                'answers as ungraded_count' => fn ($query) => $written($query->whereNull('grade_ratio')),
                'answers as ai_graded_count' => fn ($query) => $query
                    ->where('grade_source', AttemptAnswer::GRADE_SOURCE_AI),
                'answers as appeal_count' => fn ($query) => $query->whereNotNull('review_requested_at'),
            ])
            ->latest('finished_at')
            ->paginate(20);

        return Inertia::render('Admin/Grading/Index', [
            'attempts' => $attempts,
            // Xərc nəzarəti: avtomatik qiymətləndirmənin ümumi token sərfi
            'aiUsage' => $this->aiUsage(),
        ]);
    }

    /**
     * Avtomatik qiymətləndirmənin ümumi göstəriciləri.
     *
     * Token sayları cavab sətirlərində saxlanılır (uğursuz sorğular da daxil — pul onda da
     * yanır), ona görə cəm birbaşa oradan gəlir.
     *
     * @return array<string, mixed>
     */
    private function aiUsage(): array
    {
        $row = AttemptAnswer::query()
            ->whereNotNull('ai_graded_at')
            ->selectRaw('count(*) as answers')
            ->selectRaw('sum(ai_input_tokens) as input_tokens')
            ->selectRaw('sum(ai_output_tokens) as output_tokens')
            ->selectRaw('sum(case when grade_source = ? then 1 else 0 end) as graded', [AttemptAnswer::GRADE_SOURCE_AI])
            ->first();

        return [
            'enabled' => (bool) config('ai_grading.enabled') && filled(config('ai_grading.api_key')),
            'model' => (string) config('ai_grading.model'),
            'answers' => (int) ($row->answers ?? 0),
            'graded' => (int) ($row->graded ?? 0),
            'input_tokens' => (int) ($row->input_tokens ?? 0),
            'output_tokens' => (int) ($row->output_tokens ?? 0),
        ];
    }

    public function show(ExamAttempt $attempt): Response
    {
        $attempt->load(['user:id,first_name,last_name,email', 'exam:id,title,subject_id', 'exam.subject:id,name']);

        $answers = $attempt->answers()
            ->with('question:id,question_text,question_image,question_image_alt,explanation,grading_rubric,type')
            ->whereHas('question', fn ($query) => $query->where('type', Question::TYPE_OPEN_WRITTEN))
            ->get()
            ->map(fn (AttemptAnswer $answer) => [
                'id' => $answer->id,
                'question_text' => $answer->question->question_text,
                // Şəkilli sualda cavabı qiymətləndirmək üçün şəkil də lazımdır
                'question_image_url' => $answer->question->imageUrl(),
                'question_image_alt' => $answer->question->question_image_alt,
                'explanation' => $answer->question->explanation,
                'grading_rubric' => $answer->question->grading_rubric,
                'open_answer' => $answer->open_answer,
                'grade_ratio' => $answer->grade_ratio,
                'graded_at' => $answer->graded_at?->toDateTimeString(),
                // Avtomatik qiymət: admin onu görüb dəyişə bilər
                'grade_source' => $answer->grade_source,
                'grade_comment' => $answer->grade_comment,
                'ai_model' => $answer->ai_model,
                'ai_tokens' => (int) $answer->ai_input_tokens + (int) $answer->ai_output_tokens,
                'review_requested_at' => $answer->review_requested_at?->toDateTimeString(),
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
            // Adminin qiyməti avtomatik qiyməti ÜSTƏLƏYİR və şagirdin etirazını bağlayır
            'grade_source' => AttemptAnswer::GRADE_SOURCE_ADMIN,
            'graded_by' => auth()->id(),
            'graded_at' => now(),
            'review_requested_at' => null,
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
