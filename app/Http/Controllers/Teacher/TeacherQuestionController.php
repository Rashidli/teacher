<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuestionRequest;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TeacherQuestionController extends Controller
{
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

        $data = $request->validated();

        // Şəkil yükləmə
        if ($request->hasFile('question_image')) {
            $data['question_image'] = $request->file('question_image')
                ->store('questions', 'public');
        }

        $data['order'] = $exam->questions()->count() + 1;

        $question = $exam->questions()->create($data);

        // Variantları yarat
        if ($request->type === 'multiple_choice' && $request->options) {
            foreach ($request->options as $index => $optionData) {
                $optionToCreate = [
                    'option_letter' => $optionData['option_letter'],
                    'option_text' => $optionData['option_text'],
                    'is_correct' => $optionData['is_correct'] ?? false,
                    'order' => $index + 1,
                ];

                $question->options()->create($optionToCreate);
            }
        }

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

        $data = $request->validated();

        // Yeni şəkil yükləndisə
        if ($request->hasFile('question_image')) {
            // Köhnə şəkli sil
            if ($question->question_image) {
                Storage::disk('public')->delete($question->question_image);
            }
            $data['question_image'] = $request->file('question_image')
                ->store('questions', 'public');
        }

        $question->update($data);

        // Variantları yenilə
        if ($request->type === 'multiple_choice' && $request->options) {
            $question->options()->delete();

            foreach ($request->options as $index => $optionData) {
                $optionToCreate = [
                    'option_letter' => $optionData['option_letter'],
                    'option_text' => $optionData['option_text'],
                    'is_correct' => $optionData['is_correct'] ?? false,
                    'order' => $index + 1,
                ];

                $question->options()->create($optionToCreate);
            }
        }

        return redirect()->route('teacher.exams.show', $exam)
            ->with('success', 'Sual uğurla yeniləndi.');
    }

    public function destroy(Exam $exam, Question $question)
    {
        $this->authorize('update', $exam);

        // Şəkilləri sil
        if ($question->question_image) {
            Storage::disk('public')->delete($question->question_image);
        }

        $question->delete();

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
