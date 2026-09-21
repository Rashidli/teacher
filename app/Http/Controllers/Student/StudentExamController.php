<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\Group;
use App\Models\SubjectGroupScore;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentExamController extends Controller
{
    public function index(Request $request)
    {
        $query = Exam::with(['subject', 'teacher', 'group'])
            ->withCount('questions')
            ->published()
            ->active();

        if ($request->subject_id) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->group_id) {
            $query->where('group_id', $request->group_id);
        }

        $exams = $query->latest()->paginate(12);

        return Inertia::render('Student/Exams/Index', [
            'exams' => $exams,
            'subjects' => Subject::active()->get(),
            'groups' => Group::active()->orderBy('number')->get(),
            'filters' => $request->only(['subject_id', 'group_id']),
        ]);
    }

    public function show(Exam $exam)
    {
        $exam->load(['subject', 'teacher', 'group']);
        $exam->loadCount('questions');

        // Şagirdin bu imtahanda aktiv cəhdi varmı?
        $activeAttempt = auth('student')->user()->examAttempts()
            ->where('exam_id', $exam->id)
            ->inProgress()
            ->first();

        // Tamamlanmış cəhdlər
        $completedAttempts = auth('student')->user()->examAttempts()
            ->where('exam_id', $exam->id)
            ->completed()
            ->latest()
            ->get();

        return Inertia::render('Student/Exams/Show', [
            'exam' => $exam,
            'activeAttempt' => $activeAttempt,
            'completedAttempts' => $completedAttempts,
        ]);
    }

    public function start(Request $request, Exam $exam)
    {
        // Aktiv cəhd varsa yoxla
        $activeAttempt = auth('student')->user()->examAttempts()
            ->where('exam_id', $exam->id)
            ->inProgress()
            ->first();

        if ($activeAttempt) {
            // Vaxtı bitmişsə bitir və yeni cəhd yaratmağa icazə ver
            if ($activeAttempt->remaining_time <= 0) {
                $this->finishAttempt($activeAttempt);
            } else {
                // Hələ vaxt varsa, mövcud cəhdə yönləndir
                return redirect()->route('student.exams.attempt', $activeAttempt);
            }
        }

        // Yeni cəhd yarat - imtahanın öz qrupunu istifadə et
        $attempt = ExamAttempt::create([
            'user_id' => auth('student')->id(),
            'exam_id' => $exam->id,
            'group_id' => $exam->group_id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return redirect()->route('student.exams.attempt', $attempt);
    }

    public function attempt(ExamAttempt $attempt)
    {
        // Bu şagirdin cəhdi olduğunu yoxla
        if ($attempt->user_id !== auth('student')->id()) {
            abort(403);
        }

        // Vaxt bitmişsə bitir
        if ($attempt->status === 'in_progress' && $attempt->remaining_time <= 0) {
            $this->finishAttempt($attempt);
            return redirect()->route('student.exams.result', $attempt);
        }

        // Tamamlanıbsa nəticəyə yönləndir
        if ($attempt->status !== 'in_progress') {
            return redirect()->route('student.exams.result', $attempt);
        }

        $attempt->load(['exam.subject', 'group']);

        // Mövcud cavabları topla
        $existingAnswers = [];
        foreach ($attempt->answers as $answer) {
            if ($answer->selected_option_id) {
                $existingAnswers[$answer->question_id] = $answer->selected_option_id;
            }
        }

        $questions = $attempt->exam->questions()
            ->with('options')
            ->orderBy('order')
            ->get()
            ->map(function ($question) use ($attempt) {
                $answer = $attempt->answers()
                    ->where('question_id', $question->id)
                    ->first();

                return [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_image' => $question->question_image,
                    'type' => $question->type,
                    'options' => $question->options->map(fn($opt) => [
                        'id' => $opt->id,
                        'option_letter' => $opt->option_letter,
                        'option_text' => $opt->option_text,
                        'option_image' => $opt->option_image,
                    ]),
                    'selected_option_id' => $answer?->selected_option_id,
                    'open_answer' => $answer?->open_answer,
                ];
            });

        return Inertia::render('Student/Exams/Attempt', [
            'attempt' => $attempt,
            'exam' => $attempt->exam,
            'questions' => $questions,
            'answers' => $existingAnswers,
            'remainingTime' => $attempt->remaining_time,
        ]);
    }

    public function saveAnswer(Request $request, ExamAttempt $attempt)
    {
        if ($attempt->user_id !== auth('student')->id() || $attempt->status !== 'in_progress') {
            abort(403);
        }

        $request->validate([
            'question_id' => ['required', 'exists:questions,id'],
            'selected_option_id' => ['nullable', 'exists:question_options,id'],
            'open_answer' => ['nullable', 'string', 'max:5000'],
        ]);

        $attempt->answers()->updateOrCreate(
            ['question_id' => $request->question_id],
            [
                'selected_option_id' => $request->selected_option_id,
                'open_answer' => $request->open_answer,
            ]
        );

        return response()->json(['success' => true]);
    }

    public function finish(ExamAttempt $attempt)
    {
        if ($attempt->user_id !== auth('student')->id() || $attempt->status !== 'in_progress') {
            abort(403);
        }

        $this->finishAttempt($attempt);

        return redirect()->route('student.exams.result', $attempt);
    }

    private function finishAttempt(ExamAttempt $attempt): void
    {
        $exam = $attempt->exam;
        $group = $attempt->group;

        // Bu fənn-qrup üçün bal
        $scorePerQuestion = SubjectGroupScore::where('subject_id', $exam->subject_id)
            ->where('group_id', $group->id)
            ->first()?->score ?? 1;

        $correctAnswers = 0;
        $wrongAnswers = 0;
        $totalScore = 0;

        foreach ($exam->questions as $question) {
            $answer = $attempt->answers()->where('question_id', $question->id)->first();

            if (!$answer || !$answer->selected_option_id) {
                continue;
            }

            $correctOption = $question->correctOption;
            $isCorrect = $correctOption && $answer->selected_option_id === $correctOption->id;

            $answer->update([
                'is_correct' => $isCorrect,
                'score_earned' => $isCorrect ? $scorePerQuestion : 0,
            ]);

            if ($isCorrect) {
                $correctAnswers++;
                $totalScore += $scorePerQuestion;
            } else {
                $wrongAnswers++;
            }
        }

        $totalQuestions = $exam->questions()->count();
        $answeredQuestions = $attempt->answers()->whereNotNull('selected_option_id')->count();

        $attempt->update([
            'status' => 'completed',
            'finished_at' => now(),
            'time_spent_seconds' => now()->diffInSeconds($attempt->started_at),
            'total_score' => $totalScore,
            'correct_answers' => $correctAnswers,
            'wrong_answers' => $wrongAnswers,
            'unanswered' => $totalQuestions - $answeredQuestions,
        ]);
    }

    public function result(ExamAttempt $attempt)
    {
        if ($attempt->user_id !== auth('student')->id()) {
            abort(403);
        }

        $attempt->load(['exam.subject', 'exam.teacher', 'group', 'answers.question.options', 'answers.selectedOption']);

        $totalQuestions = $attempt->exam->questions()->count();

        // Sualları cavablarla birlikdə hazırla
        $questionsWithAnswers = $attempt->exam->questions()
            ->with(['options', 'correctOption'])
            ->orderBy('order')
            ->get()
            ->map(function ($question) use ($attempt) {
                $answer = $attempt->answers->where('question_id', $question->id)->first();
                $correctOption = $question->correctOption;

                return [
                    'question_id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_image' => $question->question_image,
                    'type' => $question->type,
                    'explanation' => $question->explanation,
                    'options' => $question->options->map(fn($opt) => [
                        'id' => $opt->id,
                        'option_text' => $opt->option_text,
                        'is_correct' => $correctOption && $opt->id === $correctOption->id,
                    ]),
                    'correct_option_id' => $correctOption?->id,
                    'selected_option_id' => $answer?->selected_option_id,
                    'is_correct' => $answer?->is_correct ?? false,
                    'score_earned' => $answer?->score_earned ?? 0,
                ];
            });

        // Attempt-a əlavə məlumat əlavə et
        $attemptData = $attempt->toArray();
        $attemptData['total_questions'] = $totalQuestions;
        $attemptData['score'] = $attempt->total_score;

        return Inertia::render('Student/Exams/Result', [
            'attempt' => $attemptData,
            'exam' => $attempt->exam,
            'answers' => $questionsWithAnswers,
        ]);
    }
}
