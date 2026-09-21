<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuestionRequest;
use App\Models\Exam;
use App\Models\Question;
use App\Services\QuestionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin paneldə imtahan daxilində sual idarəsi.
 * Müəllim modulu söndürülüb, ona görə suallar yalnız buradan daxil edilir.
 */
class AdminQuestionController extends Controller
{
    public function __construct(private readonly QuestionService $questions)
    {
    }

    public function create(Exam $exam): Response
    {
        return Inertia::render('Admin/Questions/Create', [
            'exam' => $exam->load('subject'),
        ]);
    }

    public function store(StoreQuestionRequest $request, Exam $exam): RedirectResponse
    {
        $this->questions->create($exam, $request->validated());

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Sual əlavə edildi.');
    }

    public function edit(Exam $exam, Question $question): Response
    {
        $this->ensureBelongsToExam($exam, $question);

        return Inertia::render('Admin/Questions/Edit', [
            'exam' => $exam->load('subject'),
            'question' => $question->load('options'),
        ]);
    }

    public function update(StoreQuestionRequest $request, Exam $exam, Question $question): RedirectResponse
    {
        $this->ensureBelongsToExam($exam, $question);

        $this->questions->update($question, $request->validated());

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Sual yeniləndi.');
    }

    public function destroy(Exam $exam, Question $question): RedirectResponse
    {
        $this->ensureBelongsToExam($exam, $question);

        $this->questions->delete($question);
        $this->questions->resequence($exam);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Sual silindi.');
    }

    /** Sualı bir mövqe yuxarı və ya aşağı sürüşdürür. */
    public function move(Exam $exam, Question $question, string $direction): RedirectResponse
    {
        $this->ensureBelongsToExam($exam, $question);

        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $this->questions->move($question, $direction);

        return back();
    }

    /** URL-dəki sual həqiqətən bu imtahana aid olmalıdır. */
    private function ensureBelongsToExam(Exam $exam, Question $question): void
    {
        abort_unless($question->exam_id === $exam->id, 404);
    }
}
