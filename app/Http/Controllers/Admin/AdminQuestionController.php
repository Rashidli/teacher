<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuestionRequest;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Topic;
use App\Services\QuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function create(Request $request, Exam $exam): Response
    {
        $sections = $exam->sections()->with('subject:id,name')->get();
        $sectionId = (int) $request->query('section_id') ?: $sections->first()?->id;

        return Inertia::render('Admin/Questions/Create', [
            'exam' => $exam->load('subject'),
            'sections' => $sections->map(fn ($section) => [
                'id' => $section->id,
                'title' => $section->displayTitle(),
            ]),
            'sectionId' => $sectionId,
            'topics' => $this->topicOptions($exam),
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
            'topics' => $this->topicOptions($exam),
            // Redaktə köhnə nəticələrə təsir edə bilər: formada xəbərdarlıq göstərilir
            'attemptUsage' => $question->attemptUsageCount(),
        ]);
    }

    public function update(StoreQuestionRequest $request, Exam $exam, Question $question): RedirectResponse
    {
        $this->ensureBelongsToExam($exam, $question);

        $this->questions->update($question, $request->validated());

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Sual yeniləndi.');
    }

    /**
     * Sualı imtahandan AYIRIR — bankdan silmir. Sual başqa imtahanlarda işlənə bilər və
     * keçilmiş cəhdlərin nəticəsi ona istinad edir.
     */
    public function destroy(Exam $exam, Question $question): RedirectResponse
    {
        $this->ensureBelongsToExam($exam, $question);

        $this->questions->detach($exam, $question);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Sual imtahandan ayrıldı (bankda qalır).');
    }

    /** Mövcud bank sualını bu imtahana bağlayır. */
    public function attach(Request $request, Exam $exam): RedirectResponse
    {
        $validated = $request->validate([
            'question_id' => ['required', Rule::exists('questions', 'id')],
            'section_id' => [
                'nullable',
                Rule::exists('exam_sections', 'id')->where('exam_id', $exam->id),
            ],
        ]);

        $section = isset($validated['section_id'])
            ? $exam->sections()->find($validated['section_id'])
            : null;

        $this->questions->attach($exam, Question::findOrFail($validated['question_id']), $section);

        return back()->with('success', 'Sual imtahana əlavə edildi.');
    }

    /**
     * Sualın kopyasını yaradıb imtahanda onunla əvəzləyir.
     * Cəhdlərdə işlənmiş sualı dəyişmək əvəzinə istifadə olunur — köhnə nəticələr toxunulmur.
     */
    public function duplicate(Exam $exam, Question $question): RedirectResponse
    {
        $this->ensureBelongsToExam($exam, $question);

        $copy = $this->questions->duplicateInto($exam, $question->load('options'));

        return redirect()->route('admin.exams.questions.edit', [$exam, $copy])
            ->with('success', 'Sualın kopyası yaradıldı və imtahanda əvəzləndi.');
    }

    /** Sualı eyni hovuzdan başqa təsadüfi sualla əvəz edir (generasiyadan sonra). */
    public function replace(Exam $exam, Question $question): RedirectResponse
    {
        $this->ensureBelongsToExam($exam, $question);

        if ($exam->is_published) {
            return back()->withErrors([
                'question' => 'Dərc olunmuş imtahanın sualları dəyişdirilmir. '
                    .'Əvvəlcə imtahanı dərcdən çıxarın.',
            ]);
        }

        $replacement = $this->questions->replaceWithRandom($exam, $question);

        return back()->with(
            $replacement ? 'success' : 'error',
            $replacement
                ? 'Sual başqası ilə əvəz edildi.'
                : 'Bankda uyğun başqa sual tapılmadı.'
        );
    }

    /** Sualı bir mövqe yuxarı və ya aşağı sürüşdürür. */
    public function move(Exam $exam, Question $question, string $direction): RedirectResponse
    {
        $this->ensureBelongsToExam($exam, $question);

        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $this->questions->move($exam, $question, $direction);

        return back();
    }

    /** İmtahanın fənninə aid mövzular */
    private function topicOptions(Exam $exam)
    {
        return Topic::active()
            ->where('subject_id', $exam->subject_id)
            ->orderBy('order')->orderBy('name')
            ->get(['id', 'name', 'quarter']);
    }

    /** URL-dəki sual həqiqətən bu imtahana bağlı olmalıdır. */
    private function ensureBelongsToExam(Exam $exam, Question $question): void
    {
        abort_unless($exam->questions()->where('questions.id', $question->id)->exists(), 404);
    }
}
