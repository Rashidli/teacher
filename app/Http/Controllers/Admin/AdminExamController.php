<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Subject;
use App\Models\Tag;
use App\Models\User;
use App\Support\Sector;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminExamController extends Controller
{
    public function index(Request $request)
    {
        $query = Exam::with(['teacher', 'creator', 'subject', 'group'])
            ->withCount('questions');

        if ($request->subject_id) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->group_id) {
            $query->where('group_id', $request->group_id);
        }

        if ($request->teacher_id) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if (Sector::isValid($request->sector)) {
            $query->where('sector', $request->sector);
        }

        // Status filtri: səhifədəki dörd seçimin hamısı emal olunur
        match ($request->status) {
            'published' => $query->where('is_published', true),
            'draft' => $query->where('is_published', false),
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };

        // withQueryString: səhifələmə keçidlərində filtrlər itmir
        $exams = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Admin/Exams/Index', [
            'exams' => $exams,
            'subjects' => Subject::active()->get(),
            'groups' => Group::active()->get(),
            'teachers' => config('features.teachers') ? User::verifiedTeachers()->get() : [],
            'filters' => $request->only(['subject_id', 'group_id', 'teacher_id', 'status', 'sector']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Exams/Create', [
            // Müəllim modulu söndürülüb olanda müəllim seçimi göstərilmir
            'teachers' => config('features.teachers') ? User::verifiedTeachers()->with('subjects')->get() : [],
            'subjects' => Subject::active()->get(),
            'groups' => Group::active()->orderBy('number')->get(),
            'categories' => $this->categoryOptions(),
            'sectors' => Sector::ALL,
        ]);
    }

    /**
     * Ödənişli imtahanın qiyməti mütləq sıfırdan böyük olmalıdır: əks halda kataloqda
     * "0 AZN" görünür və "Al" düyməsi sıfır məbləğli ödəniş yaradırdı.
     */
    private function priceRules(Request $request): array
    {
        if ($request->boolean('is_free')) {
            return ['nullable', 'numeric', 'min:0'];
        }

        return ['required', 'numeric', 'min:0.01'];
    }

    /**
     * İmtahanın bal qrupu kateqoriyadan götürülür — iki mənbə arasında ziddiyyət
     * yaranmasın (kateqoriya ağacı və bal matrisi uyğun qalsın).
     *
     * Kateqoriyanın qrupu yoxdursa (sürücülük, MİQ, sertifikasiya, magistratura, dövlət
     * qulluğu) sahə NULL qalır: DİM qrupları yalnız abituriyent qəbuluna aiddir, uydurma
     * qrup isə statistikada və hesabatlarda səhv qruplaşdırma yaradır. Formada seçim
     * edilibsə belə, kateqoriya son sözü deyir.
     */
    private function applyCategoryGroup(array $validated): array
    {
        if (empty($validated['category_id'])) {
            return $validated;
        }

        $validated['group_id'] = Category::whereKey($validated['category_id'])->value('group_id');

        return $validated;
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function tagOptions()
    {
        return Tag::active()
            ->ordered()
            ->get(['id', 'name', 'kind'])
            ->map(fn (Tag $tag) => ['id' => $tag->id, 'name' => $tag->name, 'kind' => $tag->kind]);
    }

    /** İmtahan bağlana bilən kateqoriyalar (yalnız test keçirilənlər) */
    private function categoryOptions()
    {
        return Category::active()
            ->where('has_exams', true)
            ->orderBy('path')
            ->get(['id', 'name', 'path'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'label' => $category->path.' — '.$category->name,
                /*
                 * Adı sinif bildirirsə ("9-cu sinif buraxılış") forma sinif etiketi
                 * seçiləndə xəbərdarlıq göstərir — etiket orada təkrar olur.
                 */
                'mentions_grade' => $category->mentionsGrade(),
            ]);
    }

    /** @return array<string, string> */
    private function priceMessages(): array
    {
        return [
            'price.required' => 'Ödənişli imtahan üçün qiymət göstərilməlidir.',
            'price.min' => 'Ödənişli imtahanın qiyməti sıfırdan böyük olmalıdır.',
            'price.numeric' => 'Qiymət rəqəm olmalıdır.',
        ];
    }

    /** Pulsuz imtahanda qiymət saxlanılmır. */
    private function normalisePrice(array $validated): array
    {
        if (filter_var($validated['is_free'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $validated['price'] = 0;
        }

        return $validated;
    }

    public function store(Request $request)
    {
        $teachersEnabled = (bool) config('features.teachers');

        $validated = $request->validate([
            'teacher_id' => $teachersEnabled ? ['required', 'exists:users,id'] : ['exclude'],
            'subject_id' => ['required', 'exists:subjects,id'],
            // Qrup yalnız abituriyent kateqoriyalarında olur; digərlərində NULL qalır
            'group_id' => ['nullable', 'exists:groups,id'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            // Etiketlər: sinif səviyyəsi və sərbəst etiketlər (çox-çoxa)
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:180'],
            // Tədris sektoru: imtahan yalnız bu dildə suallar qəbul edir
            'sector' => ['required', Rule::in(Sector::ALL)],
            'is_free' => ['boolean'],
            'price' => $this->priceRules($request),
        ], $this->priceMessages());

        $validated = $this->normalisePrice($this->applyCategoryGroup($validated));

        // İmtahanı yaradan admin hesabıdır. teacher_id yalnız müəllim modulunda doldurulur.
        $validated['created_by'] = $request->user()->id;
        $validated['created_by_admin'] = true;

        $tags = $validated['tags'] ?? [];
        unset($validated['tags']);

        $exam = Exam::create($validated);
        $exam->tags()->sync($tags);

        // Hər imtahanın ən azı bir bölməsi olur: bal hesablaması və səhifələr tək məntiqlə işləyir
        $exam->sections()->create([
            'subject_id' => $exam->subject_id,
            'order' => 1,
        ]);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'İmtahan uğurla yaradıldı.');
    }

    public function show(Exam $exam)
    {
        $exam->load(['teacher', 'creator', 'subject', 'group', 'category']);

        $sections = $exam->sections()->with(['subject:id,name', 'questions.options', 'questions.topic'])->get();

        return Inertia::render('Admin/Exams/Show', [
            'exam' => $exam,
            'sections' => $sections->map(fn ($section) => [
                'id' => $section->id,
                'title' => $section->displayTitle(),
                'subject' => $section->subject?->name,
                'question_count' => $section->question_count,
                'max_score' => $section->max_score,
                'order' => $section->order,
                'questions' => $section->questions->map(fn ($question) => [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_image_url' => $question->imageUrl(),
                    'question_image_alt' => $question->question_image_alt,
                    'type' => $question->type,
                    'topic' => $question->topic?->name,
                    'explanation' => $question->explanation,
                    'accepted_answers' => $question->accepted_answers,
                    'options' => $question->options->map(fn ($option) => [
                        'id' => $option->id,
                        'option_letter' => $option->option_letter,
                        'option_text' => $option->option_text,
                        'option_image_url' => $option->imageUrl(),
                        'is_correct' => $option->is_correct,
                    ]),
                ]),
            ]),
            'subjects' => Subject::active()->orderBy('order')->get(['id', 'name']),
            'questionsTotal' => $sections->sum(fn ($section) => $section->questions->count()),
        ]);
    }

    public function edit(Exam $exam)
    {
        $exam->load(['teacher', 'subject', 'group', 'tags:id']);

        return Inertia::render('Admin/Exams/Edit', [
            'exam' => $exam,
            'categories' => $this->categoryOptions(),
            'teachers' => config('features.teachers') ? User::verifiedTeachers()->with('subjects')->get() : [],
            'subjects' => Subject::active()->get(),
            'groups' => Group::active()->orderBy('number')->get(),
            'tags' => $this->tagOptions(),
            'sectors' => Sector::ALL,
            // Sual bağlandıqdan sonra sektor dəyişmir (suallar başqa dildə qalardı)
            'sectorLocked' => $exam->questions()->exists(),
        ]);
    }

    public function update(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:180'],
            'is_free' => ['boolean'],
            'price' => $this->priceRules($request),
            'is_active' => ['boolean'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            'sector' => ['nullable', Rule::in(Sector::ALL)],
            // Etiketlər: sinif səviyyəsi və sərbəst etiketlər (çox-çoxa)
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
        ], $this->priceMessages());

        // Sual bağlanmış imtahanın sektorunu dəyişmək sualların dilini imtahandan ayırardı
        if (isset($validated['sector']) && $validated['sector'] !== $exam->sector && $exam->questions()->exists()) {
            throw ValidationException::withMessages([
                'sector' => 'Sual bağlanmış imtahanın tədris sektoru dəyişdirilmir. '
                    .'Əvvəlcə sualları ayırın və ya yeni imtahan yaradın.',
            ]);
        }

        $tags = $validated['tags'] ?? [];
        unset($validated['tags']);

        $exam->update($this->normalisePrice($this->applyCategoryGroup($validated)));
        $exam->tags()->sync($tags);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'İmtahan uğurla yeniləndi.');
    }

    public function toggleActive(Exam $exam)
    {
        $exam->update(['is_active' => !$exam->is_active]);

        $message = $exam->is_active ? 'İmtahan aktivləşdirildi.' : 'İmtahan deaktiv edildi.';

        return back()->with('success', $message);
    }

    public function togglePublish(Exam $exam)
    {
        $exam->update([
            'is_published' => !$exam->is_published,
            'published_at' => !$exam->is_published ? now() : null,
        ]);

        $message = $exam->is_published ? 'İmtahan yayımlandı.' : 'İmtahan yayımdan çıxarıldı.';

        return back()->with('success', $message);
    }

    public function destroy(Exam $exam)
    {
        $exam->delete();

        return redirect()->route('admin.exams.index')
            ->with('success', 'İmtahan uğurla silindi.');
    }
}
