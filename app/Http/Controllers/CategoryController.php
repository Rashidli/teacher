<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Subject;
use App\Support\Localization;
use App\Support\Sector;
use Inertia\Inertia;
use Inertia\Response;

/**
 * İctimai kateqoriya səhifəsi.
 *
 * Route `/{path}` bütün digər route-lardan SONRA qeydiyyatdan keçir (routes/web.php sonu),
 * ona görə /login, /admin/… kimi ünvanlar buraya düşmür — onlar əvvəl uyğunlaşır.
 */
class CategoryController extends Controller
{
    /** URL sonundakı rüb seqmenti: "2-ci-rub" → 2 */
    private const QUARTER_PATTERN = '/^([1-4])-c[iıü]-rub$/u';

    private const TOPIC_TRIAL_SEGMENT = 'movzu-sinagi';

    public function show(string $path): Response
    {
        [$categoryPath, $view, $quarter] = $this->parsePath(trim($path, '/'));

        $category = Category::active()
            ->where('path', $categoryPath)
            ->firstOr(fn () => abort(404));

        $sector = Sector::current();

        $category->load([
            'children' => fn ($query) => $query->where('is_active', true),
            'subjects',
            'group',
        ]);

        return Inertia::render('Category/Show', [
            'category' => [
                'name' => $category->localized('name'),
                'short' => $category->localized('short'),
                'description' => $category->localized('description'),
                'h1' => $category->localized('h1') ?: $category->localized('name'),
                'intro' => $category->localized('intro'),
                'has_exams' => $category->has_exams,
                'url' => Localization::categoryUrl($category->path),
            ],
            'breadcrumb' => $category->ancestors()
                ->push($category)
                ->map(fn (Category $node) => [
                    'name' => $node->localized('name'),
                    'url' => Localization::categoryUrl($node->path),
                ])
                ->values(),
            'children' => $category->children->map(fn (Category $child) => [
                'name' => $child->localized('name'),
                'short' => $child->localized('short'),
                'url' => Localization::categoryUrl($child->path),
            ]),
            // Sektor: daxil olmuş istifadəçidə profildən, qonaqda sessiya/URL dilindən
            'sector' => $sector,
            'canSwitchSector' => Sector::guestCanSwitch(),
            'ruEnabled' => $category->ru_enabled,
            'subjects' => $category->subjectsForSector($sector)->map(fn (Subject $subject) => [
                'name' => $subject->name,
                'question_count' => $subject->pivot->question_count,
                'max_score' => $category->maxScoreFor($subject),
            ]),
            'view' => $view,
            'quarter' => $quarter,
            // Mövzu sınağı səhifəsində hansı rüblərdə imtahan var
            'quarters' => $view === 'topic_trial' ? $this->availableQuarters($category, $sector) : [],
            'topicTrialUrl' => Localization::categoryUrl($category->path.'/'.self::TOPIC_TRIAL_SEGMENT),
            'hasTopicTrials' => $this->topicTrialQuery($category, $sector)->exists(),
            'exams' => $this->exams($category, $view, $quarter, $sector),
            /*
             * DİQQƏT: "seo" adı istifadə edilmir — o, HandleInertiaRequests-in paylaşdığı
             * canonical/hreflang prop-udur. Üzərinə yazılsa, kateqoriya səhifələrində
             * canonical itir (TopicTrialFlowTest bunu yoxlayır).
             */
            'meta' => [
                'title' => $category->localized('seo_title') ?: $category->localized('name'),
                'description' => $category->localized('seo_description'),
            ],
        ]);
    }

    /**
     * Yol sonundakı xüsusi seqmentləri ayırır:
     *   .../rk                          → kateqoriya səhifəsi
     *   .../rk/movzu-sinagi             → rüb seçimi
     *   .../rk/movzu-sinagi/2-ci-rub    → həmin rübün imtahanları
     *
     * Rüblər üçün ayrıca kateqoriya sətri yaradılmır.
     *
     * @return array{0: string, 1: ?string, 2: ?int}
     */
    private function parsePath(string $path): array
    {
        $segments = explode('/', $path);
        $quarter = null;

        if (count($segments) >= 2 && preg_match(self::QUARTER_PATTERN, end($segments), $matches)) {
            $quarter = (int) $matches[1];
            array_pop($segments);
        }

        $view = null;

        if (end($segments) === self::TOPIC_TRIAL_SEGMENT) {
            $view = 'topic_trial';
            array_pop($segments);
        } elseif ($quarter !== null) {
            // "2-ci-rub" yalnız "movzu-sinagi"dən sonra gələ bilər
            abort(404);
        }

        return [implode('/', $segments), $view, $quarter];
    }

    /** @return array<int, array{quarter: int, url: string, exams: int}> */
    private function availableQuarters(Category $category, string $sector): array
    {
        return $this->topicTrialQuery($category, $sector)
            ->whereNotNull('quarter')
            ->selectRaw('quarter, count(*) as exams')
            ->groupBy('quarter')
            ->orderBy('quarter')
            ->get()
            ->map(fn ($row) => [
                'quarter' => (int) $row->quarter,
                'exams' => (int) $row->exams,
                'url' => Localization::categoryUrl(
                    $category->path.'/'.self::TOPIC_TRIAL_SEGMENT.'/'.$row->quarter.'-ci-rub'
                ),
            ])
            ->all();
    }

    private function topicTrialQuery(Category $category, string $sector)
    {
        return Exam::query()
            ->whereIn('category_id', $category->subtreeIds())
            ->where('sector', $sector)
            ->where('kind', Exam::KIND_TOPIC_TRIAL)
            ->where('is_published', true)
            ->where('is_active', true);
    }

    /** Kateqoriyanın özünün və bütün alt düyünlərinin satışdakı imtahanları */
    private function exams(Category $category, ?string $view = null, ?int $quarter = null, string $sector = Sector::AZ)
    {
        // Rüb seçimi səhifəsində imtahan siyahısı göstərilmir
        if ($view === 'topic_trial' && $quarter === null) {
            return collect();
        }

        return Exam::query()
            ->with(['subject:id,name', 'category:id,name,path'])
            ->withCount('questions')
            ->whereIn('category_id', $category->subtreeIds())
            // Şagird yalnız öz sektorunun imtahanlarını görür
            ->where('sector', $sector)
            ->where('is_published', true)
            ->where('is_active', true)
            ->when($view === 'topic_trial', fn ($query) => $query
                ->where('kind', Exam::KIND_TOPIC_TRIAL)
                ->where('quarter', $quarter))
            ->latest()
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'subject' => $exam->subject?->name,
                'category' => $exam->category?->name,
                'duration_minutes' => $exam->duration_minutes,
                'questions_count' => $exam->questions_count,
                'is_free' => $exam->is_free,
                'price' => $exam->price,
            ]);
    }
}
