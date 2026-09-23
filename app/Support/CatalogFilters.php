<?php

namespace App\Support;

use App\Models\Exam;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Kataloq filtrləri — həm kateqoriya səhifəsində (`CategoryController`), həm də ümumi
 * kataloqda (`ExamCatalogController`) eyni məntiq işləyir.
 *
 * Seçim URL-də query kimi qalır (`?nov=topic_trial&rub=2&fenn=3&qiymet=pulsuz`), ona görə
 * süzülmüş səhifə paylaşıla bilir. Açarlar Azərbaycan dilindədir — ünvanlar oxunaqlı qalsın.
 *
 * SAYĞACLAR ƏHATƏ ÜZRƏ hesablanır: seçilmiş çip digər ölçülərin sayğaclarını daraltmır.
 * Səbəb: əks halda hər kliklə çiplər yerini dəyişər və istifadəçi nəticəsi sıfır olan
 * dalana düşərdi. Əhatə = sektor + (kateqoriya) + (axtarış) + səhifənin öz məhdudiyyəti.
 */
class CatalogFilters
{
    public const PRICE_FREE = 'pulsuz';

    public const PRICE_PAID = 'pullu';

    /** Axtarış bundan qısa olanda tətbiq edilmir: "a" bütün kataloqu qaytarardı */
    public const SEARCH_MIN = 2;

    public const SEARCH_MAX = 80;

    /** Bütün ölçülər. Səhifə yalnız özünə lazım olanları istəyir. */
    public const FACETS = ['kateqoriya', 'nov', 'rub', 'fenn', 'qiymet', 'axtar'];

    /** Sıralama variantları (`?sirala=`) */
    public const SORT_NEW = 'yeni';

    public const SORT_FREE_FIRST = 'pulsuz';

    public const SORT_CHEAP = 'ucuz';

    public const SORT_EXPENSIVE = 'baha';

    public const SORTS = [self::SORT_NEW, self::SORT_FREE_FIRST, self::SORT_CHEAP, self::SORT_EXPENSIVE];

    /**
     * Sorğudan filtrləri oxuyur. Tanınmayan dəyər null olur — səhv query səhifəni sındırmır.
     *
     * @param  array<int, string>  $facets
     * @return array<string, mixed>
     */
    public static function parse(Request $request, array $facets = self::FACETS): array
    {
        $kind = (string) $request->query('nov');
        $kind = in_array($kind, Exam::KINDS, true) ? $kind : null;

        $quarter = (int) $request->query('rub');
        $subject = (int) $request->query('fenn');
        $category = (int) $request->query('kateqoriya');
        $price = (string) $request->query('qiymet');
        $search = trim((string) $request->query('axtar'));

        $filters = [
            'kateqoriya' => $category > 0 ? $category : null,
            'nov' => $kind,
            // Rüb yalnız mövzu sınağı seçiləndə mənalıdır
            'rub' => $kind === Exam::KIND_TOPIC_TRIAL && $quarter >= 1 && $quarter <= 4 ? $quarter : null,
            'fenn' => $subject > 0 ? $subject : null,
            'qiymet' => in_array($price, [self::PRICE_FREE, self::PRICE_PAID], true) ? $price : null,
            'axtar' => mb_strlen($search) >= self::SEARCH_MIN ? mb_substr($search, 0, self::SEARCH_MAX) : null,
        ];

        return array_intersect_key($filters, array_flip($facets));
    }

    /** Sorğudan sıralama; tanınmayan dəyər defolta (ən yeni) düşür */
    public static function sort(Request $request): string
    {
        $sort = (string) $request->query('sirala');

        return in_array($sort, self::SORTS, true) ? $sort : self::SORT_NEW;
    }

    /**
     * Sıralamanı sorğuya tətbiq edir.
     *
     * Hər variantda SON pillə `exams.id DESC`-dir: eyni qiymətli və ya eyni gün dərc
     * olunmuş imtahanların sırası səhifədən-səhifəyə dəyişməsin (səhifələmə sabit qalsın).
     */
    public static function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            self::SORT_FREE_FIRST => $query
                ->orderByDesc('exams.is_free')
                ->orderBy('exams.price')
                ->orderByDesc('exams.id'),
            self::SORT_CHEAP => $query->orderBy('exams.price')->orderByDesc('exams.id'),
            self::SORT_EXPENSIVE => $query->orderByDesc('exams.price')->orderByDesc('exams.id'),
            default => $query->orderByDesc('exams.published_at')->orderByDesc('exams.id'),
        };
    }

    /**
     * Filtrləri sorğuya tətbiq edir.
     *
     * @param  array<string, mixed>  $filters
     * @param  ?Closure(int): array<int, int>  $subtree  kateqoriya id → özü və alt düyünləri
     */
    public static function apply(Builder $query, array $filters, ?Closure $subtree = null): Builder
    {
        return $query
            ->when($filters['kateqoriya'] ?? null, fn (Builder $builder, int $id) => $builder
                ->whereIn('exams.category_id', $subtree ? $subtree($id) : [$id]))
            ->when($filters['nov'] ?? null, fn (Builder $builder, string $kind) => $builder
                ->where('exams.kind', $kind))
            ->when($filters['rub'] ?? null, fn (Builder $builder, int $quarter) => $builder
                ->where('exams.quarter', $quarter))
            /*
             * Fənn imtahanın BÖLMƏLƏRİNDƏN gəlir, `exams.subject_id`-dən yox: çoxfənli
             * imtahan (məs. I qrup sınağı) içindəki hər fənnə görə tapılmalıdır.
             */
            ->when($filters['fenn'] ?? null, fn (Builder $builder, int $subjectId) => $builder
                ->whereHas('sections', fn ($section) => $section->where('subject_id', $subjectId)))
            ->when($filters['qiymet'] ?? null, fn (Builder $builder, string $price) => $builder
                ->where('exams.is_free', $price === self::PRICE_FREE))
            ->when($filters['axtar'] ?? null, fn (Builder $builder, string $term) => $builder
                ->where('exams.title', 'like', '%'.self::escapeLike($term).'%'));
    }

    /**
     * Sayğaclı filtr variantları. Yalnız MÖVCUD variantlar qaytarılır — nəticəsi sıfır
     * olan seçim təklif edilmir.
     *
     * Kateqoriya variantları burada yoxdur: onlar kateqoriya ağacı tələb edir və ağacı
     * ümumi kataloq özü qurur (`ExamCatalogController::categoryOptions()`).
     *
     * @param  Builder  $scope  əhatə sorğusu (çiplər tətbiq edilmədən)
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function options(Builder $scope): array
    {
        return [
            'kinds' => self::kinds($scope),
            'quarters' => self::quarters($scope),
            'subjects' => self::subjects($scope),
            'prices' => self::prices($scope),
        ];
    }

    /** @return array<int, array{value: string, count: int}> */
    private static function kinds(Builder $scope): array
    {
        $counts = (clone $scope)
            ->selectRaw('exams.kind, count(*) as total')
            ->groupBy('exams.kind')
            ->pluck('total', 'kind');

        return collect(Exam::KINDS)
            ->map(fn (string $kind) => ['value' => $kind, 'count' => (int) $counts->get($kind, 0)])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values()
            ->all();
    }

    /** @return array<int, array{value: int, count: int}> */
    private static function quarters(Builder $scope): array
    {
        return (clone $scope)
            ->where('exams.kind', Exam::KIND_TOPIC_TRIAL)
            ->whereNotNull('exams.quarter')
            ->selectRaw('exams.quarter, count(*) as total')
            ->groupBy('exams.quarter')
            ->orderBy('exams.quarter')
            ->get()
            ->map(fn ($row) => ['value' => (int) $row->quarter, 'count' => (int) $row->total])
            ->all();
    }

    /**
     * Fənlər bölmələrdən yığılır. `count(distinct)` lazımdır: bir imtahanın eyni fənndə
     * yalnız bir bölməsi olsa da, join sətirləri çoxaldır.
     *
     * @return array<int, array{value: int, name: string, count: int}>
     */
    private static function subjects(Builder $scope): array
    {
        return (clone $scope)
            ->join('exam_sections', 'exam_sections.exam_id', '=', 'exams.id')
            ->join('subjects', 'subjects.id', '=', 'exam_sections.subject_id')
            ->selectRaw('subjects.id as subject_id, subjects.name as subject_name, count(distinct exams.id) as total')
            ->groupBy('subjects.id', 'subjects.name')
            ->orderBy('subjects.name')
            ->get()
            ->map(fn ($row) => [
                'value' => (int) $row->subject_id,
                'name' => $row->subject_name,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /** @return array<int, array{value: string, count: int}> */
    private static function prices(Builder $scope): array
    {
        $counts = (clone $scope)
            ->selectRaw('exams.is_free, count(*) as total')
            ->groupBy('exams.is_free')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->is_free => (int) $row->total]);

        return collect([self::PRICE_FREE => $counts->get(1, 0), self::PRICE_PAID => $counts->get(0, 0)])
            ->filter()
            ->map(fn (int $count, string $value) => ['value' => $value, 'count' => $count])
            ->values()
            ->all();
    }

    /** Axtarış mətnindəki LIKE xüsusi simvolları adi simvola çevrilir */
    private static function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
    }
}
