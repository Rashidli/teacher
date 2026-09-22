<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Subject;
use App\Support\Localization;
use App\Support\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
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

    private const PRICE_FREE = 'pulsuz';

    private const PRICE_PAID = 'pullu';

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
        $filters = $this->filters($request, $view);
        $catalog = $this->catalog($category, $view, $quarter, $sector, $filters);

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
            'exams' => $catalog['exams'],
            // Filtr paneli: yalnız mövcud variantlar, hər birinin yanında sayğac
            'filterOptions' => $catalog['filterOptions'],
            'filters' => $filters,
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

    /**
     * Kataloq filtrləri. URL-də query kimi qalır ki, süzülmüş səhifə paylaşıla bilsin:
     * `?nov=topic_trial&rub=2&fenn=3&qiymet=pulsuz`.
     *
     * @return array{nov: ?string, rub: ?int, fenn: ?int, qiymet: ?string}
     */
    private function filters(Request $request, ?string $view): array
    {
        $kind = (string) $request->query('nov');
        $quarter = (int) $request->query('rub');
        $subject = (int) $request->query('fenn');
        $price = (string) $request->query('qiymet');

        $kind = in_array($kind, Exam::KINDS, true) ? $kind : null;

        return [
            'nov' => $kind,
            // Rüb yalnız mövzu sınağı seçiləndə mənalıdır
            'rub' => $kind === Exam::KIND_TOPIC_TRIAL && $quarter >= 1 && $quarter <= 4 ? $quarter : null,
            'fenn' => $subject > 0 ? $subject : null,
            'qiymet' => in_array($price, [self::PRICE_FREE, self::PRICE_PAID], true) ? $price : null,
        ];
    }

    /**
     * Düyünün (və bütün alt düyünlərinin) imtahanları + filtr variantları.
     *
     * İmtahanlar bir dəfə yüklənir: həm süzgəc, həm də sayğaclar eyni kolleksiyadan
     * hesablanır — filtr seçimləri ilə siyahı arasında uyğunsuzluq olmur.
     *
     * @param  array{nov: ?string, rub: ?int, fenn: ?int, qiymet: ?string}  $filters
     * @return array{exams: array<int, array<string, mixed>>, filterOptions: array<string, mixed>}
     */
    private function catalog(Category $category, ?string $view, ?int $quarter, string $sector, array $filters): array
    {
        // Rüb seçimi səhifəsində imtahan siyahısı göstərilmir
        if ($view === 'topic_trial' && $quarter === null) {
            return ['exams' => [], 'filterOptions' => $this->filterOptions(collect())];
        }

        $exams = Exam::query()
            ->with(['sections.subject:id,name', 'category:id,name,path'])
            ->withCount('questions')
            ->whereIn('category_id', $category->subtreeIds())
            // Şagird yalnız öz sektorunun dərc olunmuş imtahanlarını görür
            ->visible($sector)
            ->when($view === 'topic_trial', fn ($query) => $query
                ->where('kind', Exam::KIND_TOPIC_TRIAL)
                ->where('quarter', $quarter))
            ->latest()
            ->get();

        return [
            'exams' => $this->applyFilters($exams, $filters)
                ->map(fn (Exam $exam) => [
                    'id' => $exam->id,
                    'slug' => $exam->slug,
                    'url' => $exam->publicUrl(),
                    'title' => $exam->title,
                    'kind' => $exam->kind,
                    'quarter' => $exam->quarter,
                    'subjects' => $exam->sections->map(fn ($section) => $section->subject?->name)
                        ->filter()->unique()->values()->all(),
                    'category' => $exam->category?->name,
                    'duration_minutes' => $exam->duration_minutes,
                    'questions_count' => $exam->questions_count,
                    'is_free' => $exam->is_free,
                    'price' => $exam->price,
                ])
                ->values()
                ->all(),
            'filterOptions' => $this->filterOptions($exams),
        ];
    }

    /**
     * @param  Collection<int, Exam>  $exams
     * @param  array{nov: ?string, rub: ?int, fenn: ?int, qiymet: ?string}  $filters
     * @return Collection<int, Exam>
     */
    private function applyFilters(Collection $exams, array $filters): Collection
    {
        return $exams
            ->when($filters['nov'], fn (Collection $items, string $kind) => $items->where('kind', $kind))
            ->when($filters['rub'], fn (Collection $items, int $quarter) => $items->where('quarter', $quarter))
            // Fənn imtahanın BÖLMƏLƏRİNDƏN gəlir: çoxfənli imtahan hər fənninə görə tapılmalıdır
            ->when($filters['fenn'], fn (Collection $items, int $subjectId) => $items
                ->filter(fn (Exam $exam) => $exam->hasSubject($subjectId)))
            ->when($filters['qiymet'], fn (Collection $items, string $price) => $items
                ->where('is_free', $price === self::PRICE_FREE));
    }

    /**
     * Filtr variantları sayğaclarla. Yalnız bu düyündə mövcud olanlar göstərilir —
     * nəticəsi sıfır olan seçim təklif edilmir.
     *
     * @param  Collection<int, Exam>  $exams
     * @return array<string, mixed>
     */
    private function filterOptions(Collection $exams): array
    {
        $subjects = $exams
            ->flatMap(fn (Exam $exam) => $exam->sections->map(fn ($section) => [
                'id' => (int) $section->subject_id,
                'name' => $section->subject?->name,
            ]))
            ->filter(fn (array $row) => $row['id'] > 0 && filled($row['name']));

        $trials = $exams->where('kind', Exam::KIND_TOPIC_TRIAL);

        return [
            'kinds' => collect(Exam::KINDS)
                ->map(fn (string $kind) => ['value' => $kind, 'count' => $exams->where('kind', $kind)->count()])
                ->filter(fn (array $row) => $row['count'] > 0)
                ->values()
                ->all(),
            'quarters' => $trials->pluck('quarter')
                ->filter()
                ->unique()
                ->sort()
                ->map(fn (int $quarter) => [
                    'value' => $quarter,
                    'count' => $trials->where('quarter', $quarter)->count(),
                ])
                ->values()
                ->all(),
            'subjects' => $subjects->groupBy('id')
                ->map(fn (Collection $rows, int|string $id) => [
                    'value' => (int) $id,
                    'name' => $rows->first()['name'],
                    'count' => $exams->filter(fn (Exam $exam) => $exam->hasSubject((int) $id))->count(),
                ])
                ->sortBy('name')
                ->values()
                ->all(),
            'prices' => collect([
                self::PRICE_FREE => $exams->where('is_free', true)->count(),
                self::PRICE_PAID => $exams->where('is_free', false)->count(),
            ])
                ->filter()
                ->map(fn (int $count, string $value) => ['value' => $value, 'count' => $count])
                ->values()
                ->all(),
        ];
    }
}
