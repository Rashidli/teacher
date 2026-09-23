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
 * Kateqoriya səhifəsi ağacın bir düyününü göstərir; bu səhifə isə BÜTÜN dərc olunmuş
 * imtahanları ən yenisindən başlayaraq verir və kateqoriya filtri ilə daraldılır.
 * Filtr məntiqi ortaqdır (`CatalogFilters`), ona görə iki səhifə heç vaxt fərqli
 * nəticə göstərmir.
 *
 * SEO: filtrli və səhifələnmiş ünvanların canonical-ı filtrsiz `/imtahanlar`-a göstərir —
 * `Localization::seo()` canonical-ı query string-siz yoldan qurur, yəni eyni məzmun
 * onlarla ünvanda indeksləşmir.
 */
class ExamCatalogController extends Controller
{
    /** Bir səhifədə neçə imtahan (1, 2 və 3 sütuna bərabər bölünür) */
    private const PER_PAGE = 24;

    private const PAGE_KEY = 'sehife';

    public function __invoke(Request $request): Response
    {
        $sector = Sector::current();
        $filters = CatalogFilters::parse($request);

        $tree = $this->tree();

        $scope = fn () => Exam::query()->visible($sector);

        $exams = CatalogFilters::apply($scope(), $filters, fn (int $id) => $this->subtree($tree, $id))
            ->with(['sections.subject:id,name', 'category:id,name,path'])
            ->withCount('questions')
            // Ən son dərc olunan əvvəldə; eyni vaxtda dərc olunanlar id-yə görə
            ->orderByDesc('exams.published_at')
            ->orderByDesc('exams.id')
            ->paginate(self::PER_PAGE, ['*'], self::PAGE_KEY)
            ->withQueryString();

        return Inertia::render('Exams/Index', [
            'exams' => $exams->getCollection()->map(fn (Exam $exam) => CategoryController::examCard($exam))->all(),
            'pagination' => [
                'page' => $exams->currentPage(),
                'pages' => $exams->lastPage(),
                'total' => $exams->total(),
                'prev' => $exams->previousPageUrl(),
                'next' => $exams->nextPageUrl(),
            ],
            // Sayğaclar ƏHATƏ üzrə: seçilmiş çip digər ölçüləri daraltmır
            'filterOptions' => CatalogFilters::options($scope()) + [
                'categories' => $this->categoryOptions($tree, $sector),
            ],
            'filters' => $filters,
            'sector' => $sector,
            'canSwitchSector' => Sector::guestCanSwitch(),
            'meta' => [
                'title' => __('exam_catalog.meta_title'),
                'description' => __('exam_catalog.meta_description'),
            ],
        ]);
    }

    /**
     * Kateqoriya ağacı bir sorğu ilə yüklənir (onlarla sətir) — hər düyün üçün ayrıca
     * `subtreeIds()` çağırmaq N+1 sorğu demək olardı.
     *
     * @return Collection<int, Category>
     */
    private function tree(): Collection
    {
        return Category::active()->orderBy('order')->orderBy('name')->get(['id', 'parent_id', 'name', 'path', 'translations']);
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
     * Kateqoriya filtri: kök və ikinci səviyyə düyünlər, hər birinin yanında imtahan sayı.
     * Daha dərin düyünlər siyahını uzadardı — onlara kateqoriya səhifəsindən keçilir.
     *
     * Sayğac ALT AĞACI da sayır: "Abituriyent" seçiləndə qrupların imtahanları da çıxır.
     * Boş düyün siyahıda göstərilmir.
     *
     * @param  Collection<int, Category>  $tree
     * @return array<int, array{value: int, name: string, depth: int, count: int}>
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
            $rows = [$this->categoryOption($tree, $root, 0, $counts)];

            foreach ($tree->where('parent_id', $root->id) as $child) {
                $rows[] = $this->categoryOption($tree, $child, 1, $counts);
            }

            // Kökün alt ağacı boşdursa bütün qol siyahıdan düşür
            if ($rows[0]['count'] > 0) {
                $options = array_merge($options, array_filter($rows, fn (array $row) => $row['count'] > 0));
            }
        }

        return array_values($options);
    }

    /**
     * @param  Collection<int, Category>  $tree
     * @param  Collection<int, int>  $counts
     * @return array{value: int, name: string, depth: int, count: int}
     */
    private function categoryOption(Collection $tree, Category $category, int $depth, Collection $counts): array
    {
        $total = 0;

        foreach ($this->subtree($tree, $category->id) as $id) {
            $total += (int) $counts->get($id, 0);
        }

        return [
            'value' => $category->id,
            'name' => $category->localized('name'),
            'depth' => $depth,
            'count' => $total,
        ];
    }
}
