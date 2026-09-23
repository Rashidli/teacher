<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Subject;
use App\Support\CatalogFilters;
use App\Support\Localization;
use App\Support\Sector;
use Illuminate\Database\Eloquent\Builder;
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

    /** Bu səhifədə kateqoriya və axtarış filtri yoxdur: əhatə onsuz da bu düyündür */
    private const FACETS = ['nov', 'rub', 'fenn', 'etiket', 'qiymet'];

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
        $filters = CatalogFilters::parse($request, self::FACETS);
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
     * Düyünün (və bütün alt düyünlərinin) imtahanları + filtr variantları.
     *
     * Süzgəc və sayğaclar `CatalogFilters` ilə qurulur — ümumi kataloq (`/imtahanlar`)
     * eyni məntiqi işlədir, ona görə iki səhifə heç vaxt fərqli nəticə vermir.
     *
     * @param  array<string, mixed>  $filters
     * @return array{exams: array<int, array<string, mixed>>, filterOptions: array<string, mixed>}
     */
    private function catalog(Category $category, ?string $view, ?int $quarter, string $sector, array $filters): array
    {
        // Rüb seçimi səhifəsində imtahan siyahısı göstərilmir
        if ($view === 'topic_trial' && $quarter === null) {
            return ['exams' => [], 'filterOptions' => ['kinds' => [], 'quarters' => [], 'subjects' => [], 'tags' => [], 'prices' => []]];
        }

        $scope = fn () => Exam::query()
            ->whereIn('exams.category_id', $category->subtreeIds())
            // Şagird yalnız öz sektorunun dərc olunmuş imtahanlarını görür
            ->visible($sector)
            ->when($view === 'topic_trial', fn (Builder $query) => $query
                ->where('exams.kind', Exam::KIND_TOPIC_TRIAL)
                ->where('exams.quarter', $quarter));

        $exams = CatalogFilters::apply($scope(), $filters)
            // Kateqoriya zənciri kart üçün: `trail()` əlavə sorğu etməsin
            ->with(['sections.subject:id,name', 'category.parent.parent', 'tags'])
            ->withCount('questions')
            ->latest('exams.id')
            ->get();

        return [
            'exams' => $exams->map(fn (Exam $exam) => $this->examCard($exam))->all(),
            // Sayğaclar ƏHATƏ üzrə: seçilmiş çip digər ölçüləri daraltmır
            'filterOptions' => CatalogFilters::options($scope()),
        ];
    }

    /**
     * Kataloq kartının məlumatları. `ExamCatalogController` eyni formanı qaytarır —
     * `ExamCard.vue` hər iki səhifədə işlənir.
     *
     * Kartda imtahanın ÖZ BAŞLIĞI göstərilmir: o, çox vaxt kateqoriya adı + növ sözünün
     * təkrarıdır ("[DEMO] MİQ — mövzu sınağı"). Əvəzinə üst sətirdə kateqoriya yolu,
     * başlıqda isə növ və rüb olur — eyni məlumat iki dəfə yazılmır. Tam başlıq imtahanın
     * öz səhifəsindədir; kartın linkinin `aria-label`-ı isə yol + növü birləşdirir ki,
     * ekran oxuyucusunda linklər bir-birindən seçilsin.
     *
     * @return array<string, mixed>
     */
    public static function examCard(Exam $exam): array
    {
        return [
            'id' => $exam->id,
            'slug' => $exam->slug,
            'url' => $exam->publicUrl(),
            // Bölmə yolu və rəngi: "Sürücülük › DE kateqoriyası"
            'trail' => $exam->category?->trail() ?? ['root' => null, 'leaf' => null, 'color' => null],
            'kind' => $exam->kind,
            'quarter' => $exam->quarter,
            'subjects' => $exam->sections->map(fn ($section) => $section->subject?->name)
                ->filter()->unique()->values()->all(),
            'duration_minutes' => $exam->duration_minutes,
            'questions_count' => $exam->questions_count,
            'is_free' => $exam->is_free,
            'price' => $exam->price,
            'description' => $exam->description,
            // "Ətraflı" akkordeonu: bölmələr kart daxilində açılır
            'sections' => $exam->sections->map(fn ($section) => [
                'subject' => $section->subject?->name,
                'question_count' => $section->question_count,
            ])->all(),
            // Sinif etiketi kartda nişan kimi görünür, qalanları "Ətraflı"-da
            'tags' => $exam->relationLoaded('tags')
                ? $exam->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'kind' => $tag->kind,
                ])->all()
                : [],
        ];
    }
}
