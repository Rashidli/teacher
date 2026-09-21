<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SubjectGroupScore;
use App\Models\Subject;
use App\Models\Group;
use App\Services\Payment\ExamAccessService;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Scoring\AttemptScorer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StudentExamController extends Controller
{
    public function __construct(
        private readonly ExamAccessService $access,
        private readonly PaymentGatewayFactory $gateways,
        private readonly AttemptScorer $scorer,
    ) {
    }

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

        // Kataloqda "Alınıb" / "Pulsuz" / "Al" statusu üçün
        $student = auth('student')->user();
        $exams->getCollection()->transform(function (Exam $exam) use ($student) {
            $exam->setAttribute('has_access', $this->access->allows($student, $exam));

            return $exam;
        });

        return Inertia::render('Student/Exams/Index', [
            'exams' => $exams,
            'purchasesEnabled' => $this->gateways->available(),
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

        $access = $this->access->activeAccess(auth('student')->user(), $exam);

        return Inertia::render('Student/Exams/Show', [
            'exam' => $exam,
            'activeAttempt' => $activeAttempt,
            'completedAttempts' => $completedAttempts,
            'hasAccess' => $exam->is_free || $access !== null,
            'access' => $access,
            'purchasesEnabled' => $this->gateways->available(),
        ]);
    }

    public function start(Request $request, Exam $exam)
    {
        // Pullu imtahan: aktiv giriş olmadan cəhd yaradıla bilməz
        if (! $this->access->allows(auth('student')->user(), $exam)) {
            return redirect()->route('student.exams.show', $exam)
                ->with('error', 'Bu imtahan ödənişlidir. Başlamaq üçün əvvəlcə alın.');
        }

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
        $attempt = DB::transaction(function () use ($exam) {
            $attempt = ExamAttempt::create([
                'user_id' => auth('student')->id(),
                'exam_id' => $exam->id,
                'group_id' => $exam->group_id,
                'status' => ExamAttempt::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);

            /*
             * Sual siyahısı DONDURULUR: suallar bankda paylaşıldığı üçün imtahanın dəsti sonradan
             * dəyişə bilər. Bu cəhdin səhifəsi, balı və nəticəsi həmişə bu siyahıdan işləyir.
             */
            $snapshot = $exam->questions()->get()
                ->mapWithKeys(fn ($question, $index) => [
                    $question->id => ['order' => $question->pivot->order ?: $index + 1],
                ])
                ->all();

            $attempt->questions()->attach($snapshot);

            return $attempt;
        });

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

        // Cavablar bir dəfə yüklənir (əvvəl hər sual üçün ayrıca sorğu gedirdi)
        $answersByQuestion = $attempt->answers->keyBy('question_id');

        $questions = $attempt->questions()
            ->with('options')
            ->get()
            ->map(function ($question) use ($answersByQuestion) {
                $answer = $answersByQuestion->get($question->id);

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

        $validated = $request->validate([
            // Sual mütləq bu cəhdin dondurulmuş siyahısında olmalıdır
            'question_id' => [
                'required',
                Rule::exists('attempt_questions', 'question_id')->where('attempt_id', $attempt->id),
            ],
            'selected_option_id' => ['nullable', 'integer'],
            'open_answer' => ['nullable', 'string', 'max:5000'],
        ]);

        // Variant mütləq həmin sualın variantı olmalıdır
        if (! empty($validated['selected_option_id'])) {
            $belongsToQuestion = QuestionOption::where('id', $validated['selected_option_id'])
                ->where('question_id', $validated['question_id'])
                ->exists();

            abort_unless($belongsToQuestion, 422, 'Variant bu suala aid deyil.');
        }

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

    /** Fənnin bu qrupdakı maksimal balı (nəticə səhifəsində "150-dən" kimi göstərilir) */
    private function subjectMaxScore(ExamAttempt $attempt): float
    {
        $group = $attempt->exam->group?->scoringGroup();

        $score = $group
            ? SubjectGroupScore::where('subject_id', $attempt->exam->subject_id)
                ->where('group_id', $group->id)
                ->value('max_score')
            : null;

        return (float) ($score ?? config('scoring.default_max_score', 100));
    }

    /**
     * Cəhdi bitirir və balı hesablayır. Bütün hesablama məntiqi App\Services\Scoring-dədir:
     * DİM düsturu, open_coded-in avtomatik yoxlanması, yazılı suallar üçün pending_review.
     */
    private function finishAttempt(ExamAttempt $attempt): void
    {
        if ($attempt->finished_at === null) {
            $attempt->forceFill([
                'finished_at' => now(),
                'time_spent_seconds' => max(0, now()->diffInSeconds($attempt->started_at, absolute: true)),
            ])->save();
        }

        $this->scorer->score($attempt);
    }

    public function result(ExamAttempt $attempt)
    {
        if ($attempt->user_id !== auth('student')->id()) {
            abort(403);
        }

        $attempt->load(['exam.subject', 'exam.teacher', 'group', 'answers.question.options', 'answers.selectedOption']);

        // Nəticə cəhdin dondurulmuş sual siyahısından qurulur, imtahanın cari dəstindən yox
        $totalQuestions = $attempt->questions()->count();

        $questionsWithAnswers = $attempt->questions()
            ->with(['options', 'correctOption'])
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
                    'is_correct' => $answer?->is_correct,
                    'score_earned' => $answer?->score_earned ?? 0,
                    // Açıq suallar
                    'open_answer' => $answer?->open_answer,
                    'accepted_answers' => $question->type === Question::TYPE_OPEN_CODED
                        ? $question->accepted_answers
                        : null,
                    'grade_ratio' => $answer?->grade_ratio,
                    'awaiting_review' => $question->type === Question::TYPE_OPEN_WRITTEN
                        && $answer?->grade_ratio === null,
                ];
            });

        // Attempt-a əlavə məlumat əlavə et
        $attemptData = $attempt->toArray();
        $attemptData['total_questions'] = $totalQuestions;
        $attemptData['score'] = $attempt->total_score;
        // Yazılı suallar yoxlanana qədər bal müvəqqətidir
        $attemptData['awaiting_review'] = $attempt->status === ExamAttempt::STATUS_PENDING_REVIEW;
        $attemptData['max_subject_score'] = $this->subjectMaxScore($attempt);

        return Inertia::render('Student/Exams/Result', [
            'attempt' => $attemptData,
            'exam' => $attempt->exam,
            'answers' => $questionsWithAnswers,
        ]);
    }
}
