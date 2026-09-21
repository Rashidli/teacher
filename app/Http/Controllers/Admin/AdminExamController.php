<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminExamController extends Controller
{
    public function index(Request $request)
    {
        $query = Exam::with(['teacher', 'subject', 'group'])
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
            'filters' => $request->only(['subject_id', 'group_id', 'teacher_id', 'status']),
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
            // Forma doldurulmamışdan əvvəl xəbərdarlıq göstərilsin
            'ownerConfigured' => $this->examOwnerId() !== null,
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
     * Kateqoriyanın bal qrupu varsa, imtahanın qrupu ondan götürülür — iki mənbə
     * arasında ziddiyyət yaranmasın (kateqoriya ağacı və bal matrisi uyğun qalsın).
     */
    private function applyCategoryGroup(array $validated): array
    {
        if (empty($validated['category_id'])) {
            return $validated;
        }

        $groupId = Category::whereKey($validated['category_id'])->value('group_id');

        if ($groupId) {
            $validated['group_id'] = $groupId;
        }

        return $validated;
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

    /**
     * Müəllim modulu söndürülüb olanda imtahanın sahibi EXAM_OWNER_ID-dir.
     * Təyin olunmayıbsa və ya belə istifadəçi yoxdursa null qaytarır.
     */
    private function examOwnerId(): ?int
    {
        if (config('features.teachers')) {
            return null;
        }

        $ownerId = config('features.exam_owner_id');

        return $ownerId && User::whereKey($ownerId)->exists() ? (int) $ownerId : null;
    }

    public function store(Request $request)
    {
        $teachersEnabled = (bool) config('features.teachers');

        $validated = $request->validate([
            'teacher_id' => $teachersEnabled ? ['required', 'exists:users,id'] : ['exclude'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'group_id' => ['required', 'exists:groups,id'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:180'],
            'is_free' => ['boolean'],
            'price' => $this->priceRules($request),
        ], $this->priceMessages());

        $validated = $this->normalisePrice($this->applyCategoryGroup($validated));

        // Müəllim modulu söndürülüb: imtahanın sahibi EXAM_OWNER_ID (admin hesabı).
        // Konfiqurasiya yoxdursa 500 yox, formada aydın mesaj göstərilir.
        if (! $teachersEnabled) {
            $ownerId = $this->examOwnerId();

            if ($ownerId === null) {
                throw ValidationException::withMessages([
                    'exam_owner' => 'İmtahan sahibi təyin olunmayıb: .env faylında EXAM_OWNER_ID '
                        .'mövcud admin hesabının ID-si olmalıdır. Dəyişiklikdən sonra `php artisan config:clear`.',
                ]);
            }

            $validated['teacher_id'] = $ownerId;
        }

        $validated['created_by_admin'] = true;

        $exam = Exam::create($validated);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'İmtahan uğurla yaradıldı.');
    }

    public function show(Exam $exam)
    {
        $exam->load(['teacher', 'subject', 'group', 'category', 'questions.options', 'questions.topic']);

        return Inertia::render('Admin/Exams/Show', [
            'exam' => $exam,
        ]);
    }

    public function edit(Exam $exam)
    {
        $exam->load(['teacher', 'subject', 'group']);

        return Inertia::render('Admin/Exams/Edit', [
            'exam' => $exam,
            'categories' => $this->categoryOptions(),
            'teachers' => config('features.teachers') ? User::verifiedTeachers()->with('subjects')->get() : [],
            'subjects' => Subject::active()->get(),
            'groups' => Group::active()->orderBy('number')->get(),
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
        ], $this->priceMessages());

        $exam->update($this->normalisePrice($this->applyCategoryGroup($validated)));

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
