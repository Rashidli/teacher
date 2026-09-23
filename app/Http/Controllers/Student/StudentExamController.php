<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SubjectGroupScore;
use App\Services\Grading\OpenAnswerGradingQueue;
use App\Services\Payment\ExamAccessService;
use App\Services\Scoring\AttemptScorer;
use App\Services\Statistics\StudentStatistics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StudentExamController extends Controller
{
    public function __construct(
        private readonly ExamAccessService $access,
        private readonly AttemptScorer $scorer,
        private readonly StudentStatistics $statistics,
        private readonly OpenAnswerGradingQueue $aiGrading,
    ) {}

    /**
     * "Mənim imtahanlarım": davam edən cəhdlər, giriş hüququ olan imtahanlar və nəticələr.
     *
     * Burada kataloq YOXDUR — yeni imtahan kateqoriya ağacından tapılır (ictimai səhifələr).
     * Köhnə fənn/qrup filtrli siyahı bu səbəbdən silinib.
     */
    public function index()
    {
        $student = auth()->user();

        $attempts = $student->examAttempts()
            ->with(['exam:id,slug,title,duration_minutes,category_id', 'exam.category.parent.parent'])
            ->latest()
            ->get()
            ->filter(fn (ExamAttempt $attempt) => $attempt->exam !== null);

        $inProgress = $attempts
            ->where('status', ExamAttempt::STATUS_IN_PROGRESS)
            ->filter(fn (ExamAttempt $attempt) => $attempt->remaining_time > 0)
            ->map(fn (ExamAttempt $attempt) => [
                'attempt_id' => $attempt->id,
                'title' => $attempt->exam->title,
                'url' => route('student.exams.attempt', $attempt),
                'exam_url' => $attempt->exam->publicUrl(),
                'remaining_minutes' => (int) ceil($attempt->remaining_time / 60),
                'trail' => $attempt->exam->category?->trail(),
            ])
            ->values();

        $completed = $attempts
            ->whereIn('status', [ExamAttempt::STATUS_COMPLETED, ExamAttempt::STATUS_TIMED_OUT])
            ->map(fn (ExamAttempt $attempt) => [
                'attempt_id' => $attempt->id,
                'title' => $attempt->exam->title,
                'url' => route('student.exams.result', $attempt),
                'exam_url' => $attempt->exam->publicUrl(),
                'relative_score' => $attempt->relative_score,
                'finished_at' => $attempt->finished_at?->format('d.m.Y'),
                'trail' => $attempt->exam->category?->trail(),
            ])
            ->values();

        // Girişi olan, amma hazırda davam edən cəhdi olmayan imtahanlar
        $startedExamIds = $attempts
            ->where('status', ExamAttempt::STATUS_IN_PROGRESS)
            ->filter(fn (ExamAttempt $attempt) => $attempt->remaining_time > 0)
            ->pluck('exam_id');

        $available = ExamAccess::query()
            ->with(['exam:id,slug,title,duration_minutes,is_free,sector,is_published,is_active,category_id', 'exam.category.parent.parent'])
            ->where('user_id', $student->id)
            ->active()
            ->latest()
            ->get()
            ->filter(fn (ExamAccess $access) => $access->exam !== null
                && ! $startedExamIds->contains($access->exam_id))
            ->map(fn (ExamAccess $access) => [
                'title' => $access->exam->title,
                'url' => $access->exam->publicUrl(),
                'source' => $access->source,
                'expires_at' => $access->expires_at?->format('d.m.Y'),
                'trail' => $access->exam->category?->trail(),
            ])
            ->values();

        return Inertia::render('Student/Exams/MyExams', [
            'inProgress' => $inProgress,
            'available' => $available,
            'completed' => $completed,
        ]);
    }

    /**
     * Köhnə kabinet imtahan səhifəsi. İctimai `/imtahan/{slug}` onu əvəz etdi:
     * yadda qalmış keçidlər və köhnə ödəniş bildirişləri sınmasın deyə 301 verilir.
     */
    public function show(Exam $exam)
    {
        return redirect()->to($exam->publicUrl(), 301);
    }

    public function start(Request $request, Exam $exam)
    {
        // Pullu imtahan: aktiv giriş olmadan cəhd yaradıla bilməz
        if (! $this->access->allows(auth()->user(), $exam)) {
            return redirect()->to($exam->publicUrl())
                ->with('error', 'Bu imtahan ödənişlidir. Başlamaq üçün əvvəlcə alın.');
        }

        // Aktiv cəhd varsa yoxla
        $activeAttempt = auth()->user()->examAttempts()
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
                'user_id' => auth()->id(),
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
                    $question->id => [
                        'section_id' => $question->pivot->section_id,
                        'order' => $question->pivot->order ?: $index + 1,
                    ],
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
        if ($attempt->user_id !== auth()->id()) {
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

        $sectionTitles = \App\Models\ExamSection::whereIn(
            'id',
            $attempt->questions()->pluck('attempt_questions.section_id')->filter()->unique()
        )->with('subject:id,name')->get()->mapWithKeys(
            fn ($section) => [$section->id => $section->displayTitle()]
        );

        $questions = $attempt->questions()
            ->with('options')
            ->get()
            ->map(function ($question) use ($answersByQuestion, $sectionTitles) {
                $answer = $answersByQuestion->get($question->id);

                return [
                    'id' => $question->id,
                    'section_id' => $question->pivot->section_id,
                    'section_title' => $sectionTitles->get($question->pivot->section_id),
                    'question_text' => $question->question_text,
                    'question_image_url' => $question->imageUrl(),
                    'question_image_alt' => $question->question_image_alt,
                    'type' => $question->type,
                    'options' => $question->options->map(fn ($opt) => [
                        'id' => $opt->id,
                        'option_letter' => $opt->option_letter,
                        'option_text' => $opt->option_text,
                        'option_image_url' => $opt->imageUrl(),
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
        if ($attempt->user_id !== auth()->id() || $attempt->status !== 'in_progress') {
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
        if ($attempt->user_id !== auth()->id() || $attempt->status !== 'in_progress') {
            abort(403);
        }

        $this->finishAttempt($attempt);

        return redirect()->route('student.exams.result', $attempt);
    }

    /**
     * Yoxlanılmamış yazılı cavabların gətirə biləcəyi ƏLAVƏ bal (100-lük şkalada).
     *
     * Şagird ilkin balı yekun bal sanırdı. İndi səhifə konkret rəqəm göstərir:
     * "yoxlanılan 2 sual üçün əlavə 20 bala qədər gələ bilər".
     *
     * Hesablama balın özü ilə eyni yolla gedir: bölmədə bir XAM bal vahidi
     * `max_score / rawMax` qədər fənn balı verir (bax `ScoringResult::subjectPointsPerRawPoint()`),
     * ona görə gözləyən sualların xam çəkisi həmin əmsalla vurulur.
     *
     * @return array{count: int, max_relative: float}
     */
    private function pendingPotential(ExamAttempt $attempt): array
    {
        $weight = (float) config('scoring.open_written_weight', 2);
        $graded = $attempt->answers->keyBy('question_id');
        $sectionMax = $attempt->sectionResults()->pluck('max_score', 'section_id');

        $count = 0;
        $extra = 0.0;
        $total = 0.0;

        foreach ($attempt->questions()->get()->groupBy(fn ($question) => $question->pivot->section_id) as $sectionId => $questions) {
            $max = (float) ($sectionMax[$sectionId] ?? 0);
            $total += $max;

            // Bölmənin xam məxrəci: qapalı və qısa cavab 1, yazılı cavab `weight` qədər
            $rawMax = $questions->sum(fn ($question) => $question->type === Question::TYPE_OPEN_WRITTEN ? $weight : 1.0);

            $pending = $questions
                ->filter(fn ($question) => $question->type === Question::TYPE_OPEN_WRITTEN
                    && $graded->get($question->id)?->grade_ratio === null);

            $count += $pending->count();

            if ($rawMax > 0) {
                $extra += $pending->count() * $weight * $max / $rawMax;
            }
        }

        return [
            'count' => $count,
            'max_relative' => $total > 0 ? round($extra * 100 / $total, 1) : 0.0,
        ];
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

        /*
         * Açıq yazılı cavablar avtomatik qiymətləndirmə növbəsinə düşür. Növbə işləmirsə
         * və ya API açarı yoxdursa heç nə dəyişmir: cavablar `pending_review` qalır və
         * admin əl ilə qiymətləndirir.
         */
        $this->aiGrading->dispatchFor($attempt->fresh());
    }

    /**
     * "Yenidən baxılsın": şagird avtomatik verilmiş qiymətə etiraz edir.
     *
     * Bal DƏYİŞMİR və cəhdin statusu oynamır — yalnız bayraq qoyulur və cavab adminin
     * qiymətləndirmə növbəsində görünür. Yalnız AI-nin verdiyi qiymətə etiraz edilə bilər:
     * admin qiymət veribsə, qərar onundur.
     */
    public function requestReview(ExamAttempt $attempt, AttemptAnswer $answer): RedirectResponse
    {
        abort_unless($attempt->user_id === auth()->id(), 403);
        abort_unless($answer->attempt_id === $attempt->id, 404);
        abort_unless($answer->gradedByAi(), 422, 'Bu cavabın qiyməti avtomatik verilməyib.');

        if ($answer->review_requested_at === null) {
            $answer->forceFill(['review_requested_at' => now()])->save();
        }

        return back()->with('success', 'Cavab yenidən baxışa göndərildi.');
    }

    public function result(ExamAttempt $attempt)
    {
        if ($attempt->user_id !== auth()->id()) {
            abort(403);
        }

        $attempt->load(['user:id,first_name,last_name', 'exam.subject', 'exam.teacher', 'exam.category.parent.parent', 'group', 'answers.question.options', 'answers.selectedOption']);

        // "Yenidən imtahan ver" ictimai imtahan səhifəsinə aparır (kabinetdə ayrıca səhifə yoxdur)
        $examUrl = $attempt->exam->publicUrl();

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
                    'question_image_url' => $question->imageUrl(),
                    'question_image_alt' => $question->question_image_alt,
                    'type' => $question->type,
                    'explanation' => $question->explanation,
                    'options' => $question->options->map(fn ($opt) => [
                        'id' => $opt->id,
                        'option_letter' => $opt->option_letter,
                        'option_text' => $opt->option_text,
                        'option_image_url' => $opt->imageUrl(),
                        'is_correct' => $correctOption && $opt->id === $correctOption->id,
                    ]),
                    /*
                     * Sualın XAM dəyəri: qapalı və qısa cavab 1 bal, yazılı cavab isə
                     * `scoring.open_written_weight` (2 bal). İmtahan vərəqində göstərilir —
                     * tiplərin çəkisi fərqli olduğu üçün şagird hansı sualın nə qədər
                     * "ağır" olduğunu görməlidir.
                     */
                    'weight' => $question->type === Question::TYPE_OPEN_WRITTEN
                        ? (float) config('scoring.open_written_weight', 2)
                        : 1.0,
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
                    // Avtomatik qiymət: şagird izahı görür və yenidən baxış istəyə bilər
                    'grade_source' => $answer?->grade_source,
                    'grade_comment' => $answer?->grade_comment,
                    'review_requested' => $answer?->review_requested_at !== null,
                    'answer_id' => $answer?->id,
                    'grading_rubric' => $question->type === Question::TYPE_OPEN_WRITTEN
                        ? $question->grading_rubric
                        : null,
                ];
            });

        // Attempt-a əlavə məlumat əlavə et
        $attemptData = $attempt->toArray();
        $attemptData['total_questions'] = $totalQuestions;
        $attemptData['score'] = $attempt->total_score;
        // Yazılı suallar yoxlanana qədər bal müvəqqətidir
        $attemptData['awaiting_review'] = $attempt->status === ExamAttempt::STATUS_PENDING_REVIEW;

        // "Əlavə N bala qədər gələ bilər" — ilkin bal yekun sanılmasın
        $pending = $this->pendingPotential($attempt);
        $attemptData['pending_review_count'] = $pending['count'];
        $attemptData['pending_max_relative'] = $pending['max_relative'];
        // Ümumi maksimum bölmələrin cəmindən gəlir (sabit rəqəm yazılmır)
        $attemptData['max_subject_score'] = (float) $attempt->sectionResults()->sum('max_score')
            ?: $this->subjectMaxScore($attempt);
        // İmtahan vərəqinin başlığı üçün
        $attemptData['student'] = $attempt->user?->full_name;
        $attemptData['minutes_spent'] = (int) round($attempt->time_spent_seconds / 60);
        $attemptData['trail'] = $attempt->exam->category?->trail();

        return Inertia::render('Student/Exams/Result', [
            'attempt' => $attemptData,
            'exam' => array_merge($attempt->exam->toArray(), [
                'subject' => $attempt->exam->subject,
                // Qrupsuz imtahanlarda (MİQ, sürücülük) başlıq altında bölmə adı göstərilir
                'category' => $attempt->exam->category?->trail()['root'] ?? null,
            ]),
            'examUrl' => $examUrl,
            'answers' => $questionsWithAnswers,
            // Eyni imtahanın əvvəlki cəhdləri ilə müqayisə və mövzu bölgüsü (Mərhələ 7)
            'comparison' => $this->statistics->examComparison(auth()->user(), $attempt),
            'topics' => $this->statistics->attemptTopics($attempt),
            // Fənn-fənn bölgü (hesablama anında dondurulub)
            'sections' => $attempt->sectionResults()->with('subject:id,name')->get()
                ->map(fn ($section) => [
                    'title' => $section->title ?: $section->subject?->name,
                    'question_count' => $section->question_count,
                    'correct_answers' => $section->correct_answers,
                    'wrong_answers' => $section->wrong_answers,
                    'unanswered' => $section->unanswered,
                    'relative_score' => $section->relative_score,
                    'subject_score' => $section->subject_score,
                    'max_score' => $section->max_score,
                ]),
        ]);
    }
}
