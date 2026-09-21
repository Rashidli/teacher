<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Group;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * İmtahan kateqoriyalarının ağacı. `path` üzrə idempotent upsert — təkrar işlədilə bilər.
 *
 * Qeydlər:
 * - Mövcud ünvanlar qorunur: `mekteb`, `miq`, `suruculuk-imtahani`. MİQ ağacda "Müəllimlər"in
 *   altındadır, amma `path` sütunu sayəsində URL-i `/miq` olaraq qalır.
 * - Abituriyent qrup düyünləri `group` açarı ilə mövcud `groups` sətirlərinə bağlanır —
 *   bal hesablaması orada qalır, burada təkrarlanmır.
 * - `subjects` açarı kateqoriya ↔ fənn pivotunu doldurur. `max_score` yalnız qrupa bağlı
 *   OLMAYAN kateqoriyalarda göstərilir (qrupdakılar `subject_group_scores`-dan gəlir).
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $this->createTree($this->tree());
    }

    private function tree(): array
    {
        return [
            [
                'slug' => 'mekteb', 'name' => 'Orta məktəb',
                'short' => '9 və 11-ci sinif buraxılış imtahanları',
                'translations' => ['ru' => ['name' => 'Школьник', 'short' => '9 и 11 классы, выпускные']],
                'children' => [
                    ['slug' => '9-cu-sinif-buraxilis', 'name' => '9-cu sinif buraxılış'],
                    ['slug' => '11-ci-sinif-buraxilis', 'name' => '11-ci sinif buraxılış'],
                    [
                        'slug' => 'elave-tedris-dili', 'name' => 'Əlavə tədris dili imtahanı',
                        'children' => [
                            ['slug' => '9-illik', 'name' => '9 illik'],
                            ['slug' => '11-illik', 'name' => '11 illik'],
                        ],
                    ],
                    [
                        // Yalnız rus sektoru üçün — sektor bayrağı Mərhələ 5-də əlavə olunacaq
                        'slug' => 'azerbaycan-dili-dovlet-dili',
                        'name' => 'Azərbaycan dili (dövlət dili kimi)',
                        'is_active' => false,
                        'description' => 'Rus sektoru üçün. Sektor dəstəyi əlavə olunanda aktivləşəcək.',
                    ],
                ],
            ],
            [
                'slug' => 'abituriyent', 'name' => 'Abituriyent',
                'short' => 'Bakalavr qəbulu: I mərhələ və I–V qruplar',
                'translations' => ['ru' => ['name' => 'Абитуриент', 'short' => 'Вступительный экзамен, группы I–V']],
                'children' => [
                    ['slug' => '1-ci-merhele', 'name' => 'I mərhələ', 'group' => 'I-MERHELE',
                        // Xarici dil imtahanı hər dil üçün ayrıca yaradılır (generasiyada tək seçim)
                        'subjects' => ['azerbaycan-dili' => [], 'riyaziyyat' => [],
                            'ingilis-dili' => [], 'rus-dili' => [], 'fransiz-dili' => [], 'alman-dili' => []]],
                    [
                        'slug' => '1-ci-qrup', 'name' => 'I qrup', 'group' => 'I',
                        'subjects' => ['riyaziyyat' => [], 'fizika' => [], 'kimya' => [], 'informatika' => []],
                        'children' => [
                            ['slug' => 'rk', 'name' => 'RK altqrupu', 'group' => 'I-RK',
                                'subjects' => ['riyaziyyat' => [], 'fizika' => [], 'kimya' => []]],
                            ['slug' => 'ri', 'name' => 'Rİ altqrupu', 'group' => 'I-RI',
                                'subjects' => ['riyaziyyat' => [], 'fizika' => [], 'informatika' => []]],
                        ],
                    ],
                    ['slug' => '2-ci-qrup', 'name' => 'II qrup', 'group' => 'II',
                        'subjects' => ['riyaziyyat' => [], 'tarix' => [], 'cografiya' => []]],
                    [
                        'slug' => '3-cu-qrup', 'name' => 'III qrup', 'group' => 'III',
                        'subjects' => ['azerbaycan-dili' => [], 'edebiyyat' => [], 'cografiya' => [], 'tarix' => []],
                        'children' => [
                            ['slug' => 'dt', 'name' => 'DT altqrupu', 'group' => 'III-DT',
                                'subjects' => ['azerbaycan-dili' => [], 'edebiyyat' => [], 'tarix' => []]],
                            ['slug' => 'tc', 'name' => 'TC altqrupu', 'group' => 'III-TC',
                                'subjects' => ['azerbaycan-dili' => [], 'cografiya' => [], 'tarix' => []]],
                        ],
                    ],
                    ['slug' => '4-cu-qrup', 'name' => 'IV qrup', 'group' => 'IV',
                        'subjects' => ['fizika' => [], 'kimya' => [], 'biologiya' => []]],
                    [
                        'slug' => '5-ci-qrup', 'name' => 'V qrup', 'group' => 'V',
                        'has_exams' => false,
                        'description' => 'Qabiliyyət qrupu: idman, təsviri sənət, musiqi, '
                            .'teatr/kino/xoreoqrafiya. Test yoxdur, yalnız məlumat.',
                    ],
                    ['slug' => 'kollec', 'name' => 'Kollec (orta ixtisas) qəbulu'],
                ],
            ],
            [
                'slug' => 'magistratura', 'name' => 'Magistratura',
                'short' => 'Magistraturaya qəbul imtahanı',
                'translations' => ['ru' => ['name' => 'Магистратура', 'short' => 'Логика, информатика, ин. язык']],
                'children' => [
                    [
                        'slug' => 'fenn-bloklari', 'name' => 'Fənn blokları',
                        'children' => [
                            ['slug' => 'mentiq', 'name' => 'Məntiq', 'subjects' => ['mentiq' => []]],
                            ['slug' => 'informatika', 'name' => 'İnformatika', 'subjects' => ['informatika' => []]],
                            ['slug' => 'xarici-dil', 'name' => 'Xarici dil'],
                        ],
                    ],
                    ['slug' => 'tam-sinaq', 'name' => 'Tam sınaq'],
                ],
            ],
            [
                'slug' => 'dovlet-qullugu', 'name' => 'Dövlət qulluğu',
                'short' => 'Dövlət qulluğuna qəbul imtahanı',
                'translations' => ['ru' => ['name' => 'Госслужба', 'short' => 'Законодательство, логика']],
                // Fənlər və sual sayları (variant sayı fənnə görə fərqlidir)
                'subjects' => [
                    'qanunvericilik' => ['question_count' => 40, 'options_per_question' => 4, 'max_score' => 40],
                    'mentiq' => ['question_count' => 30, 'options_per_question' => 5, 'max_score' => 30],
                    'azerbaycan-dili' => ['question_count' => 15, 'options_per_question' => 5, 'max_score' => 15],
                    'informatika' => ['question_count' => 15, 'options_per_question' => 5, 'max_score' => 15],
                ],
                'children' => [
                    [
                        'slug' => 'tam-sinaq', 'name' => 'Qruplar üzrə tam sınaq',
                        'children' => [
                            ['slug' => 'bb-ac', 'name' => 'BB və AC qrupları',
                                'description' => '100 qapalı sual (1 bal) — maksimum 100 bal.'],
                            ['slug' => 'ba-ab', 'name' => 'BA və AB qrupları',
                                'description' => '80 qapalı + 20 açıq sual (açıq 2 bal) — maksimum 120 bal.'],
                            ['slug' => 'aa', 'name' => 'AA qrupu',
                                'description' => '60 qapalı + 40 açıq sual (açıq 2 bal).'],
                        ],
                    ],
                    ['slug' => 'fealiyyetin-davam-etdirilmesi', 'name' => 'Fəaliyyətin davam etdirilməsi imtahanı'],
                ],
            ],
            [
                'slug' => 'muellimler', 'name' => 'Müəllimlər',
                'short' => 'MİQ, sertifikasiya, diaqnostik qiymətləndirmə',
                'translations' => ['ru' => ['name' => 'Учителя', 'short' => 'MİQ, сертификация, диагностика']],
                'children' => [
                    // Mövcud ünvan qorunur: ağacda burada, URL-də kökdə
                    ['slug' => 'miq', 'name' => 'MİQ (müəllimlərin işə qəbulu)', 'path' => 'miq',
                        'translations' => ['ru' => ['name' => 'MİQ', 'short' => 'Приём учителей на работу']]],
                    ['slug' => 'sertifikasiya', 'name' => 'Sertifikasiya',
                        'description' => '60 sual: fənn, metodika və təlim strategiyaları.'],
                    ['slug' => 'diaqnostik-qiymetlendirme', 'name' => 'Diaqnostik qiymətləndirmə'],
                    ['slug' => 'mektebeqeder', 'name' => 'Məktəbəqədər (bağça tərbiyəçiləri)'],
                ],
            ],
            [
                'slug' => 'suruculuk-imtahani', 'name' => 'Sürücülük vəsiqəsi',
                'short' => 'Nəzəri imtahan: yol nişanları, qaydalar, ilk yardım',
                'translations' => ['ru' => ['name' => 'Водительские права', 'short' => 'Теория, дорожные знаки']],
                'children' => [
                    [
                        'slug' => 'kateqoriyalar', 'name' => 'Kateqoriyalar',
                        'children' => array_map(
                            fn (string $code) => ['slug' => mb_strtolower($code), 'name' => $code.' kateqoriyası'],
                            ['A', 'B', 'C', 'D', 'BE', 'CE', 'DE']
                        ),
                    ],
                    ['slug' => 'movzu-testleri', 'name' => 'Mövzu testləri',
                        'description' => 'Mövzular admin paneldən redaktə olunur (Mərhələ 2).'],
                    ['slug' => 'biletler', 'name' => 'İmtahan biletləri / tam sınaq'],
                ],
            ],
            [
                // Struktur yaradılır, amma hələ deaktivdir
                'slug' => 'diger', 'name' => 'Digər imtahanlar', 'is_active' => false,
                'children' => [
                    ['slug' => 'rezidentura', 'name' => 'Rezidentura', 'is_active' => false],
                    ['slug' => 'doktorantura-xarici-dil', 'name' => 'Doktorantura xarici dil', 'is_active' => false],
                    ['slug' => 'adsi', 'name' => 'ADSİ (Azərbaycan dili sertifikasiyası)', 'is_active' => false],
                    ['slug' => 'beynelxalq-imtahanlar', 'name' => 'Beynəlxalq imtahanlar (IELTS, TOEFL, SAT)', 'is_active' => false],
                    ['slug' => 'huquq', 'name' => 'Hüquq (vəkillik, hakimlik, notariat)', 'is_active' => false],
                    ['slug' => 'olimpiadalar', 'name' => 'Olimpiadalar', 'is_active' => false],
                ],
            ],
        ];
    }

    private function createTree(array $nodes, ?Category $parent = null): void
    {
        foreach ($nodes as $index => $node) {
            $children = $node['children'] ?? [];
            $subjects = $node['subjects'] ?? [];
            unset($node['children'], $node['subjects']);

            $category = $this->upsert($node, $parent, $index);

            $this->syncSubjects($category, $subjects);
            $this->createTree($children, $category);
        }
    }

    private function upsert(array $node, ?Category $parent, int $index): Category
    {
        $groupId = isset($node['group'])
            ? Group::where('code', $node['group'])->value('id')
            : null;

        unset($node['group']);

        $path = $node['path'] ?? trim(($parent?->path ? $parent->path.'/' : '').$node['slug'], '/');

        $category = Category::firstOrNew(['path' => $path]);

        // Struktur hər dəfə yenilənir
        $category->fill($node + [
            'parent_id' => $parent?->id,
            'group_id' => $groupId,
            'path' => $path,
            'order' => $index + 1,
        ]);

        // Bayraqlar yalnız yaradılanda təyin olunur: seeder təkrar işlədiləndə adminin
        // deaktiv etdiyi kateqoriya geri açılmasın (siyahıda açıq göstərilənlərdən başqa).
        if (! $category->exists) {
            $category->is_active = $node['is_active'] ?? true;
            $category->has_exams = $node['has_exams'] ?? true;
        }

        $category->save();

        return $category;
    }

    /** @param  array<string, array<string, int|float>>  $subjects  slug => pivot dəyərləri */
    private function syncSubjects(Category $category, array $subjects): void
    {
        if ($subjects === []) {
            return;
        }

        $ids = Subject::whereIn('slug', array_keys($subjects))->pluck('id', 'slug');
        $pivot = [];
        $order = 0;

        foreach ($subjects as $slug => $values) {
            if ($ids->has($slug)) {
                $pivot[$ids->get($slug)] = $values + ['order' => ++$order];
            }
        }

        $category->subjects()->sync($pivot);
    }
}
