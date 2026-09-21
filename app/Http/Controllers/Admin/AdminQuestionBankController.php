<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\QuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Sual bankı: bütün suallar fənn, mövzu, tip və çətinliyə görə süzülür.
 * Buradan sual silinə bilər (cəhdlərdə işlənməyibsə) — imtahandan ayırma isə imtahan səhifəsindədir.
 */
class AdminQuestionBankController extends Controller
{
    public function __construct(private readonly QuestionService $questions)
    {
    }

    public function index(Request $request): Response
    {
        $questions = Question::query()
            ->with(['subject:id,name', 'topic:id,name'])
            ->withCount('exams')
            ->when($request->subject_id, fn ($query, $id) => $query->where('subject_id', $id))
            ->when($request->topic_id, fn ($query, $id) => $query->where('topic_id', $id))
            ->when($request->type, fn ($query, $type) => $query->where('type', $type))
            ->when($request->difficulty, fn ($query, $value) => $query->where('difficulty', $value))
            ->when($request->search, fn ($query, $text) => $query->where('question_text', 'like', '%'.$text.'%'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Question $question) => [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'type' => $question->type,
                'difficulty' => $question->difficulty,
                'subject' => $question->subject?->name,
                'topic' => $question->topic?->name,
                'exams_count' => $question->exams_count,
                'attempt_usage' => $question->attemptUsageCount(),
            ]);

        // İmtahan səhifəsindən gəliblərsə, hər sətirdə "bu imtahana əlavə et" düyməsi görünür
        $exam = $request->exam_id ? Exam::find($request->exam_id) : null;

        return Inertia::render('Admin/QuestionBank/Index', [
            'questions' => $questions,
            'targetExam' => $exam ? [
                'id' => $exam->id,
                'title' => $exam->title,
                'question_ids' => $exam->questions()->pluck('questions.id'),
            ] : null,
            'subjects' => Subject::active()->orderBy('order')->get(['id', 'name']),
            'topics' => Topic::active()
                ->when($request->subject_id, fn ($query, $id) => $query->where('subject_id', $id))
                ->orderBy('name')->get(['id', 'name', 'subject_id']),
            'filters' => $request->only(['subject_id', 'topic_id', 'type', 'difficulty', 'search', 'exam_id']),
        ]);
    }

    public function destroy(Question $question): RedirectResponse
    {
        try {
            $this->questions->delete($question);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['question' => $exception->getMessage()]);
        }

        return back()->with('success', 'Sual bankdan silindi.');
    }
}
