<?php

namespace App\Services\ExamGeneration;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Support\QuestionTypes;
use App\Support\Sector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bankdan imtahan generasiyası.
 *
 * Admin kateqoriyanı (qrup və ya altqrup), rübü və hər fənn üçün sual sayını seçir;
 * sistem uyğun sualları təsadüfi seçib bölmələrə yığır.
 *
 * Qaydalar:
 * - Bir generasiyada yaradılan variantlar arasında suallar TƏKRARLANMIR: 3 variant × 12 sual
 *   üçün bankda 36 unikal sual olmalıdır. Şagird A və B variantlarını alsa, eyni sualı görməz.
 * - Bank çatmırsa heç nə yaradılmır, hansı fəndə neçə sual çatmadığı qaytarılır.
 * - Yaradılan imtahan QARALAMADIR: admin baxıb sualları əvəz edə bilər, sonra dərc edir.
 */
class ExamGenerator
{
    /**
     * @param  array<int, int>  $counts  subject_id => sual sayı
     * @param  array<string, mixed>  $attributes  başlıq, müddət, variant sayı və s.
     */
    public function generate(
        Category $category,
        array $counts,
        ?int $quarter,
        bool $cumulative,
        int $variants,
        array $attributes,
    ): GenerationResult {
        $sector = $attributes['sector'] ?? Sector::AZ;
        $counts = array_filter($counts, fn ($count) => (int) $count > 0);

        if ($counts === []) {
            return new GenerationResult(collect(), [[
                'subject' => 'Fənlər',
                'requested' => 0,
                'available' => 0,
                'missing' => 0,
            ]]);
        }

        $subjects = Subject::whereIn('id', array_keys($counts))->get()->keyBy('id');
        $pools = [];
        $shortfalls = [];

        // İmtahan növünə görə icazəli sual tipləri: sürücülükdə və MİQ-də açıq sual yoxdur
        $allowedTypes = QuestionTypes::forCategory($category);

        foreach ($counts as $subjectId => $count) {
            $required = (int) $count * $variants;
            $pool = $this->pool((int) $subjectId, $quarter, $cumulative, $sector, $allowedTypes);

            if ($pool->count() < $required) {
                $shortfalls[] = [
                    'subject' => $subjects[$subjectId]->name ?? 'Fənn #'.$subjectId,
                    'requested' => $required,
                    'available' => $pool->count(),
                    'missing' => $required - $pool->count(),
                ];

                continue;
            }

            // Təsadüfi qarışdırılır: hər variant öz payını ardıcıl götürür, təkrar olmur
            $pools[$subjectId] = $pool->shuffle()->values();
        }

        if ($shortfalls !== []) {
            return new GenerationResult(collect(), $shortfalls);
        }

        return new GenerationResult(
            DB::transaction(fn () => $this->createExams(
                $category, $counts, $pools, $quarter, $cumulative, $variants, $attributes
            ))
        );
    }

    /**
     * Seçilmiş fənn, rüb və İCAZƏLİ TİPLƏRƏ uyğun aktiv sualların ID-ləri.
     *
     * @param  array<int, string>  $allowedTypes
     */
    private function pool(int $subjectId, ?int $quarter, bool $cumulative, string $sector, array $allowedTypes): Collection
    {
        return Question::query()
            ->where('subject_id', $subjectId)
            // Yalnız imtahanın sektorunun dilindəki suallar
            ->where('language', $sector)
            ->whereIn('type', $allowedTypes)
            ->where('is_active', true)
            ->when($quarter !== null, fn ($query) => $query->whereHas(
                'topic',
                fn ($topic) => $cumulative
                    ? $topic->where('quarter', '<=', $quarter)
                    : $topic->where('quarter', $quarter)
            ))
            ->pluck('id');
    }

    /**
     * @param  array<int, int>  $counts
     * @param  array<int, Collection>  $pools
     * @return Collection<int, Exam>
     */
    private function createExams(
        Category $category,
        array $counts,
        array $pools,
        ?int $quarter,
        bool $cumulative,
        int $variants,
        array $attributes,
    ): Collection {
        $exams = collect();

        foreach (range(1, $variants) as $variant) {
            $exam = Exam::create([
                'created_by' => $attributes['created_by'] ?? null,
                // Müəllim modulu açıq olanda imtahan konkret müəllimə bağlana bilər
                'teacher_id' => $attributes['teacher_id'] ?? null,
                'subject_id' => (int) array_key_first($counts),
                'group_id' => $category->group_id ?? $attributes['group_id'] ?? null,
                'category_id' => $category->id,
                'kind' => $quarter !== null ? Exam::KIND_TOPIC_TRIAL : Exam::KIND_GENERAL,
                'sector' => $attributes['sector'] ?? Sector::AZ,
                'quarter' => $quarter,
                'is_cumulative' => $cumulative,
                'title' => $this->title($attributes['title'], $variant, $variants),
                'duration_minutes' => $attributes['duration_minutes'],
                'options_per_question' => $attributes['options_per_question'],
                'is_free' => $attributes['is_free'] ?? true,
                'price' => $attributes['is_free'] ?? true ? 0 : ($attributes['price'] ?? 0),
                // Qaralama: admin baxıb dərc edəcək
                'is_active' => false,
                'is_published' => false,
                'created_by_admin' => true,
            ]);

            $order = 0;

            foreach ($counts as $subjectId => $count) {
                $section = $exam->sections()->create([
                    'subject_id' => $subjectId,
                    'question_count' => $count,
                    'order' => ++$order,
                ]);

                // Hər variant hovuzdan öz payını götürür: A 0–11, B 12–23, C 24–35
                $slice = $pools[$subjectId]->slice(($variant - 1) * $count, $count)->values();

                foreach ($slice as $index => $questionId) {
                    $exam->questions()->attach($questionId, [
                        'section_id' => $section->id,
                        'order' => $index + 1,
                    ]);
                }
            }

            $exams->push($exam);
        }

        return $exams;
    }

    private function title(string $base, int $variant, int $variants): string
    {
        if ($variants === 1) {
            return $base;
        }

        return $base.' ('.chr(64 + $variant).')';
    }
}
