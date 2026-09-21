<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Subject;
use App\Services\ExamGeneration\ExamGenerator;
use App\Support\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Bankdan imtahan yarat": admin kateqoriya, rüb və hər fənn üçün sual sayını seçir,
 * sistem bankdan uyğun sualları təsadüfi seçib qaralama imtahan(lar) qurur.
 *
 * Xarici dil imtahanı hər dil üçün ayrıca yaradılır: bir imtahana yalnız bir dil düşə bilər.
 */
class AdminExamGenerationController extends Controller
{
    public function __construct(private readonly ExamGenerator $generator)
    {
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Exams/Generate', [
            // Fənn siyahısı sektora görə fərqlənir (ana dili): hər sektor ayrıca göndərilir
            'categories' => Category::active()
                ->where('has_exams', true)
                ->with('subjects:id,name,is_language')
                ->has('subjects')
                ->orderBy('path')
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'label' => $category->path.' — '.$category->name,
                    // Pivotda sector = null olan fənn hər iki sektora aiddir
                    'subjects' => collect(Sector::ALL)
                        ->mapWithKeys(fn (string $sector) => [
                            $sector => $category->subjects
                                ->filter(fn (Subject $subject) => $subject->pivot->sector === null
                                    || $subject->pivot->sector === $sector)
                                ->map(fn (Subject $subject) => [
                                    'id' => $subject->id,
                                    'name' => $subject->name,
                                    'is_language' => $subject->is_language,
                                ])->values(),
                        ]),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', Rule::exists('categories', 'id')],
            // Sektor həm sual hovuzunu (questions.language), həm də yaradılan imtahanı təyin edir
            'sector' => ['required', Rule::in(Sector::ALL)],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'is_cumulative' => ['boolean'],
            'variants' => ['required', 'integer', 'min:1', 'max:10'],
            'title' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:300'],
            'options_per_question' => ['required', 'integer', 'in:4,5'],
            'counts' => ['required', 'array', 'min:1'],
            'counts.*' => ['nullable', 'integer', 'min:0', 'max:200'],
        ], [
            'counts.required' => 'Ən azı bir fənn üçün sual sayı göstərilməlidir.',
        ]);

        $category = Category::with('subjects')->findOrFail($validated['category_id']);
        $counts = $this->countsForCategory($category, $validated['counts'], $validated['sector']);

        $result = $this->generator->generate(
            category: $category,
            counts: $counts,
            quarter: $validated['quarter'] ?? null,
            cumulative: (bool) ($validated['is_cumulative'] ?? false),
            variants: (int) $validated['variants'],
            attributes: [
                'teacher_id' => auth('admin')->id(),
                'title' => $validated['title'],
                'duration_minutes' => (int) $validated['duration_minutes'],
                'options_per_question' => (int) $validated['options_per_question'],
                'sector' => $validated['sector'],
                'is_free' => true,
            ],
        );

        if ($result->failed()) {
            // Heç nə yaradılmayıb: hansı fəndə neçə sual çatmadığı göstərilir
            return back()->withInput()->withErrors([
                'bank' => implode(' ', $result->shortfallMessages()),
            ]);
        }

        $first = $result->exams->first();
        $count = $result->exams->count();

        return redirect()->route('admin.exams.show', $first)->with(
            'success',
            $count === 1
                ? 'Qaralama imtahan yaradıldı. Sualları yoxlayıb dərc edin.'
                : "{$count} qaralama variant yaradıldı (suallar təkrarlanmır). Yoxlayıb dərc edin."
        );
    }

    /**
     * Yalnız kateqoriyanın həmin sektordakı fənləri qəbul olunur və xarici dillərdən
     * yalnız biri seçilə bilər.
     *
     * @param  array<int|string, mixed>  $counts
     * @return array<int, int>
     */
    private function countsForCategory(Category $category, array $counts, string $sector): array
    {
        $allowed = $category->subjectsForSector($sector)->keyBy('id');
        $result = [];
        $languages = [];

        foreach ($counts as $subjectId => $count) {
            $subjectId = (int) $subjectId;
            $count = (int) $count;

            if ($count <= 0) {
                continue;
            }

            if (! $allowed->has($subjectId)) {
                throw ValidationException::withMessages([
                    'counts' => 'Seçilmiş fənn bu kateqoriyanın seçilmiş sektoruna aid deyil.',
                ]);
            }

            if ($allowed[$subjectId]->is_language) {
                $languages[] = $allowed[$subjectId]->name;
            }

            $result[$subjectId] = $count;
        }

        if (count($languages) > 1) {
            throw ValidationException::withMessages([
                'counts' => 'Bir imtahana yalnız bir xarici dil düşə bilər. Seçilib: '
                    .implode(', ', $languages).'. Hər dil üçün ayrıca imtahan yaradın.',
            ]);
        }

        if ($result === []) {
            throw ValidationException::withMessages([
                'counts' => 'Ən azı bir fənn üçün sual sayı göstərilməlidir.',
            ]);
        }

        return $result;
    }
}
