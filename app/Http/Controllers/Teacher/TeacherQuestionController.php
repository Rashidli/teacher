<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuestionRequest;
use App\Models\Exam;
use App\Models\Question;
use App\Services\QuestionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeacherQuestionController extends Controller
{
    public function __construct(private readonly QuestionService $questions)
    {
    }

    public function create(Exam $exam)
    {
        $this->authorize('update', $exam);

        return Inertia::render('Teacher/Questions/Create', [
            'exam' => $exam->load('subject'),
        ]);
    }

    public function store(StoreQuestionRequest $request, Exam $exam)
    {
        $this->authorize('update', $exam);

        $this->questions->create($exam, $request->validated());

        return redirect()->route('teacher.exams.show', $exam)
            ->with('success', 'Sual uğurla əlavə edildi.');
    }

    public function edit(Exam $exam, Question $question)
    {
        $this->authorize('update', $exam);

        $question->load('options');

        return Inertia::render('Teacher/Questions/Edit', [
            'exam' => $exam->load('subject'),
            'question' => $question,
        ]);
    }

    public function update(StoreQuestionRequest $request, Exam $exam, Question $question)
    {
        $this->authorize('update', $exam);

        $this->questions->update($question, $request->validated());

        return redirect()->route('teacher.exams.show', $exam)
            ->with('success', 'Sual uğurla yeniləndi.');
    }

    public function destroy(Exam $exam, Question $question)
    {
        $this->authorize('update', $exam);

        $this->questions->detach($exam, $question);

        return redirect()->route('teacher.exams.show', $exam)
            ->with('success', 'Sual uğurla silindi.');
    }

    public function reorder(Request $request, Exam $exam)
    {
        $this->authorize('update', $exam);

        $request->validate([
            'questions' => ['required', 'array'],
            'questions.*.id' => ['required', 'exists:questions,id'],
            'questions.*.order' => ['required', 'integer'],
        ]);

        foreach ($request->questions as $item) {
            Question::where('id', $item['id'])
                ->where('exam_id', $exam->id)
                ->update(['order' => $item['order']]);
        }

        return response()->json(['success' => true]);
    }
}
