<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Subject;
use App\Support\Localization;
use App\Support\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function show(Request $request, string $path): Response|RedirectResponse
    {
        [$categoryPath, $view, $quarter] = $this->parsePath(trim($path, '/'));

        $locale = app()->getLocale();
        $category = $this->resolve($categoryPath, $locale);

        // Rusca ünvanı olan düyünə Azərbaycan yolu ilə gəliblərsə, tək ünvan saxlanılır
        if ($category->pathFor($locale) !== $categoryPath) {
            return redirect()->to($this->pageUrl($category, $locale, $view, $quarter), 301);
        }

        $sector = Sector::current();

        $this->shareSeo($request, $category, $view, $quarter);

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
                'url' => $category->urlFor($locale),
            ],
            'breadcrumb' => $category->ancestors()
                ->push($category)
                ->map(fn (Category $node) => [
                    'name' => $node->localized('name'),
                    'url' => $node->urlFor($locale),
                ])
                ->values(),
            'children' => $category->children->map(fn (Category $child) => [
                'name' => $child->localized('name'),
                'short' => $child->localized('short'),
                'url' => $child->urlFor($locale),
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
            'quarters' => $view === 'topic_trial' ? $this->availableQuarters($category, $sector, $locale) : [],
            'topicTrialUrl' => $this->pageUrl($category, $locale, 'topic_trial'),
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

    /**
     * Kateqoriyanı ünvana görə tapır. Rus dilində əvvəlcə `ru_path` yoxlanılır,
     * tapılmasa Azərbaycan yolu ilə açılır (sonra kanonik ünvana yönləndirilir).
     */
    private function resolve(string $path, string $locale): Category
    {
        if ($locale !== Localization::default()) {
            $translated = Category::active()->where('ru_path', $path)->first();

            if ($translated) {
                return $translated;
            }
        }

        return Category::active()->where('path', $path)->firstOr(fn () => abort(404));
    }

    /** Səhifənin tam ünvanı: kateqoriya + mövzu sınağı/rüb seqmentləri */
    private function pageUrl(Category $category, string $locale, ?string $view = null, ?int $quarter = null): string
    {
        $path = $category->pathFor($locale);

        if ($view === 'topic_trial') {
            $path .= '/'.self::TOPIC_TRIAL_SEGMENT;

            if ($quarter !== null) {
                $path .= '/'.$quarter.'-ci-rub';
            }
        }

        return Localization::categoryUrl($path, $locale);
    }

    /**
     * Canonical/hreflang və JSON-LD sorğu atributlarında saxlanılır: həm Inertia-nın
     * paylaşdığı `seo` prop-u, həm də `partials/seo` Blade şablonu oradan oxuyur.
     * Beləliklə kateqoriya səhifəsi `seo` prop-unu üzərinə yazmır.
     */
    private function shareSeo(Request $request, Category $category, ?string $view, ?int $quarter): void
    {
        $alternates = [];

        foreach (Localization::supported() as $locale) {
            $alternates[$locale] = $this->pageUrl($category, $locale, $view, $quarter);
        }

        $request->attributes->set(Localization::ALTERNATES_ATTRIBUTE, $alternates);
        $request->attributes->set(
            Localization::JSON_LD_ATTRIBUTE,
            $this->breadcrumbJsonLd($category, $view, $quarter),
        );
    }

    /** schema.org BreadcrumbList: axtarış nəticələrində yol zənciri görünsün */
    private function breadcrumbJsonLd(Category $category, ?string $view, ?int $quarter): array
    {
        $locale = app()->getLocale();

        $items = collect([['name' => __('category_page.home'), 'url' => Localization::route('home', [], true, $locale)]]);

        foreach ($category->ancestors()->push($category) as $node) {
            $items->push(['name' => $node->localized('name'), 'url' => $node->urlFor($locale)]);
        }

        if ($view === 'topic_trial') {
            $items->push([
                'name' => __('category_page.topic_trial'),
                'url' => $this->pageUrl($category, $locale, 'topic_trial'),
            ]);

            if ($quarter !== null) {
                $items->push([
                    'name' => $quarter.'-ci rüb',
                    'url' => $this->pageUrl($category, $locale, 'topic_trial', $quarter),
                ]);
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items->values()->map(fn (array $item, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }

    /** @return array<int, array{quarter: int, url: string, exams: int}> */
    private function availableQuarters(Category $category, string $sector, string $locale): array
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
                'url' => $this->pageUrl($category, $locale, 'topic_trial', (int) $row->quarter),
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
