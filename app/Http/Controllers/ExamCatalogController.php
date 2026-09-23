<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Exam;
use App\Support\CatalogFilters;
use App\Support\Sector;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ümumi kataloq: `/imtahanlar` (rusca `/ru/imtahanlar`).
 *
 * İKİ GÖRÜNÜŞ:
 *  - `grouped` (defolt): kök bölmələr üzrə qruplaşdırılmış — hər bölmədən bir neçə imtahan
 *    və "Hamısına bax (N)" keçidi. Beləcə bir bölmə (məs. sürücülük kateqoriyaları) bütün
 *    səhifəni tutmur. Sıralama seçimi bu görünüşdə də işləyir: hər bölmənin içi seçilmiş
 *    sıra ilə düzülür.
 *  - `list`: filtr və ya axtarış seçiləndə — səhifələnən düz siyahı.
 *
 * Filtr məntiqi kateqoriya səhifəsi ilə ortaqdır (`CatalogFilters`), ona görə iki səhifə
 * heç vaxt fərqli nəticə göstərmir.
 *
 * SEO: filtrli və səhifələnmiş ünvanların canonical-ı filtrsiz `/imtahanlar`-a göstərir —
 * `Localization::seo()` canonical-ı query string-siz yoldan qurur.
 */
class ExamCatalogController extends Controller
{
    /** Siyahı görünüşündə bir səhifədə neçə imtahan (1, 2 və 3 sütuna bərabər bölünür) */
    private const PER_PAGE = 24;

    /** Qruplaşdırılmış görünüşdə hər bölmədən neçə imtahan göstərilir */
    private const PER_GROUP = 4;

    private const PAGE_KEY = 'sehife';

    public function __invoke(Request $request): Response
    {
        $sector = Sector::current();
        $filters = CatalogFilters::parse($request);
        $sort = CatalogFilters::sort($request);

        $tree = $this->tree();
        $scope = fn () => Exam::query()->visible($sector);

        // Filtr və ya axtarış seçiləndə düz siyahıya keçilir; təkcə sıralama görünüşü dəyişmir
        $grouped = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty();

        return Inertia::render('Exams/Index', [
            'mode' => $grouped ? 'grouped' : 'list',
            'groups' => $grouped ? $this->groups($tree, $sector, $sort) : [],
            ...$grouped ? ['exams' => [], 'pagination' => null] : $this->list($scope(), $filters, $sort, $tree),
            // Sayğaclar ƏHATƏ üzrə: seçilmiş çip digər ölçüləri daraltmır
            'filterOptions' => CatalogFilters::options($scope()) + [
                'categories' => $this->categoryOptions($tree, $sector),
            ],
            'filters' => $filters,
            'sort' => $sort,
            'sorts' => CatalogFilters::SORTS,
            'sector' => $sector,
            'canSwitchSector' => Sector::guestCanSwitch(),
            'meta' => [
                'title' => __('exam_catalog.meta_title'),
                'description' => __('exam_catalog.meta_description'),
            ],
        ]);
    }

    /**
     * Süzülmüş, sıralanmış və səhifələnmiş siyahı.
     *
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, Category>  $tree
     * @return array{exams: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    private function list($query, array $filters, string $sort, Collection $tree): array
    {
        $paginator = CatalogFilters::applySort(
            CatalogFilters::apply($query, $filters, fn (int $id) => $this->subtree($tree, $id)),
            $sort,
        )
            ->with($this->cardRelations())
            ->withCount('questions')
            ->paginate(self::PER_PAGE, ['*'], self::PAGE_KEY)
            ->withQueryString();

        return [
            'exams' => $paginator->getCollection()
                ->map(fn (Exam $exam) => CategoryController::examCard($exam))
                ->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'pages' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];
    }

    /**
     * Kök bölmələr üzrə qruplar: hər birində ilk bir neçə imtahan və ümumi say.
     *
     * Bölmənin imtahanları BİR sorğu ilə yığılır (alt ağac ID-ləri ilə), sonra PHP-də
     * qruplara bölünür — hər kök üçün ayrıca sorğu getmir.
     *
     * @param  Collection<int, Category>  $tree
     * @return array<int, array<string, mixed>>
     */
    private function groups(Collection $tree, string $sector, string $sort): array
    {
        $roots = $tree->whereNull('parent_id');

        if ($roots->isEmpty()) {
            return [];
        }

        // Hər imtahanın hansı kökə aid olduğunu tapmaq üçün: kateqoriya id → kök id
        $rootOf = [];

        foreach ($roots as $root) {
            foreach ($this->subtree($tree, $root->id) as $id) {
                $rootOf[$id] = $root->id;
            }
        }

        $exams = CatalogFilters::applySort(Exam::query()->visible($sector), $sort)
            ->whereIn('exams.category_id', array_keys($rootOf))
            ->with($this->cardRelations())
            ->withCount('questions')
            ->get()
            ->groupBy(fn (Exam $exam) => $rootOf[$exam->category_id] ?? 0);

        $groups = [];

        foreach ($roots as $root) {
            $items = $exams->get($root->id, collect());

            if ($items->isEmpty()) {
                continue;
            }

            $groups[] = [
                'id' => $root->id,
                'name' => $root->localized('name'),
                'short' => $root->localized('short'),
                'color' => $root->color,
                'url' => $root->urlFor(),
                'total' => $items->count(),
                'exams' => $items->take(self::PER_GROUP)
                    ->map(fn (Exam $exam) => CategoryController::examCard($exam))
                    ->values()
                    ->all(),
            ];
        }

        return $groups;
    }

    /**
     * Kart üçün lazım olan münasibətlər. Kateqoriya zənciri iki səviyyə yüklənir —
     * ağac üç səviyyədən dərin deyil, ona görə `rootAncestor()` əlavə sorğu etmir.
     *
     * @return array<int|string, mixed>
     */
    private function cardRelations(): array
    {
        return ['sections.subject:id,name', 'category.parent.parent', 'tags'];
    }

    /**
     * Kateqoriya ağacı bir sorğu ilə yüklənir (onlarla sətir) — hər düyün üçün ayrıca
     * `subtreeIds()` çağırmaq N+1 sorğu demək olardı.
     *
     * @return Collection<int, Category>
     */
    private function tree(): Collection
    {
        return Category::active()
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'short', 'path', 'ru_path', 'color', 'translations']);
    }

    /**
     * Düyün və bütün alt düyünlərinin ID-ləri — yaddaşdakı ağacdan.
     *
     * @param  Collection<int, Category>  $tree
     * @return array<int, int>
     */
    private function subtree(Collection $tree, int $id): array
    {
        $ids = [$id];
        $level = [$id];

        while ($level !== []) {
            $level = $tree->whereIn('parent_id', $level)->pluck('id')->all();
            $ids = array_merge($ids, $level);
        }

        return $ids;
    }

    /**
     * Kateqoriya filtri: kök düyünlər və onların birbaşa övladları (akkordeon).
     * Daha dərin düyünlərə kateqoriya səhifəsindən keçilir.
     *
     * Sayğac ALT AĞACI da sayır: "Abituriyent" seçiləndə qrupların imtahanları da çıxır.
     * Boş qol siyahıda göstərilmir.
     *
     * @param  Collection<int, Category>  $tree
     * @return array<int, array<string, mixed>>
     */
    private function categoryOptions(Collection $tree, string $sector): array
    {
        $counts = Exam::query()
            ->visible($sector)
            ->whereNotNull('exams.category_id')
            ->selectRaw('exams.category_id, count(*) as total')
            ->groupBy('exams.category_id')
            ->pluck('total', 'category_id');

        $options = [];

        foreach ($tree->whereNull('parent_id') as $root) {
            $total = $this->countFor($tree, $root->id, $counts);

            if ($total === 0) {
                continue;
            }

            $children = [];

            foreach ($tree->where('parent_id', $root->id) as $child) {
                $childTotal = $this->countFor($tree, $child->id, $counts);

                if ($childTotal > 0) {
                    $children[] = [
                        'value' => $child->id,
                        'name' => $child->localized('name'),
                        'count' => $childTotal,
                    ];
                }
            }

            $options[] = [
                'value' => $root->id,
                'name' => $root->localized('name'),
                'color' => $root->color,
                'count' => $total,
                'children' => $children,
            ];
        }

        return $options;
    }

    /**
     * @param  Collection<int, Category>  $tree
     * @param  Collection<int, int>  $counts
     */
    private function countFor(Collection $tree, int $categoryId, Collection $counts): int
    {
        $total = 0;

        foreach ($this->subtree($tree, $categoryId) as $id) {
            $total += (int) $counts->get($id, 0);
        }

        return $total;
    }
}
