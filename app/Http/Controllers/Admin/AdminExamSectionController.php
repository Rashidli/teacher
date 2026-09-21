<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * İmtahanın fənn bölmələri. Çoxfənli imtahan hər fənn üçün bir bölmədən ibarətdir;
 * tək-fənli imtahanın da bir bölməsi olur, ona görə ayrıca "bölməsiz" rejim yoxdur.
 */
class AdminExamSectionController extends Controller
{
    public function store(Request $request, Exam $exam): RedirectResponse
    {
        $validated = $request->validate([
            'subject_id' => [
                'required',
                Rule::exists('subjects', 'id'),
                // Bir fənn imtahanda yalnız bir dəfə ola bilər
                Rule::unique('exam_sections', 'subject_id')->where('exam_id', $exam->id),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'question_count' => ['nullable', 'integer', 'min:1', 'max:200'],
            'max_score' => ['nullable', 'numeric', 'min:1', 'max:9999'],
        ], [
            'subject_id.unique' => 'Bu fənn artıq imtahanın bölmələrindədir.',
        ]);

        $exam->sections()->create($validated + [
            'order' => (int) ($exam->sections()->max('order') ?? 0) + 1,
        ]);

        return back()->with('success', 'Bölmə əlavə edildi.');
    }

    public function update(Request $request, Exam $exam, ExamSection $section): RedirectResponse
    {
        abort_unless($section->exam_id === $exam->id, 404);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'question_count' => ['nullable', 'integer', 'min:1', 'max:200'],
            'max_score' => ['nullable', 'numeric', 'min:1', 'max:9999'],
            'order' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $section->update($validated);

        return back()->with('success', 'Bölmə yeniləndi.');
    }

    public function destroy(Exam $exam, ExamSection $section): RedirectResponse
    {
        abort_unless($section->exam_id === $exam->id, 404);

        if ($exam->sections()->count() === 1) {
            return back()->withErrors([
                'section' => 'İmtahanın ən azı bir bölməsi olmalıdır.',
            ]);
        }

        if ($section->questions()->exists()) {
            return back()->withErrors([
                'section' => 'Bölmədə suallar var. Əvvəlcə onları ayırın və ya başqa bölməyə keçirin.',
            ]);
        }

        $section->delete();

        return back()->with('success', 'Bölmə silindi.');
    }
}
