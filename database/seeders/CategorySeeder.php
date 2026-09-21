<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Group;
use App\Models\Subject;
use App\Support\Sector;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
 *   Ana dili sektora görə dəyişdikdə açar sektorla verilir: ['az' => [...], 'ru' => [...]].
 * - `ru_enabled` rus sektoru keçidini göstərir; övladlar valideyndən miras alır.
 * - `ru_path` rusca ünvandır (`/ru/abiturient/1-ya-gruppa`). Hissələr RU_SLUGS-dan gəlir,
 *   yoxdursa Azərbaycan slug-ı işlənir. Düyün `ru_path` açarı ilə öz yolunu təyin edə bilər.
 */
class CategorySeeder extends Seeder
{
    /**
     * Rusca ünvan hissələri: Azərbaycan slug-ı → rus slug-ı (ASCII translit).
     * Siyahıda olmayan düyün Azərbaycan slug-ını saxlayır.
     */
    private const RU_SLUGS = [
        // Kök bölmələr
        'mekteb' => 'shkola',
        'abituriyent' => 'abiturient',
        'magistratura' => 'magistratura',
        'dovlet-qullugu' => 'gossluzhba',
        'muellimler' => 'uchitelya',
        'suruculuk-imtahani' => 'voditelskie-prava',
        'diger' => 'drugie',

        // Orta məktəb
        '9-cu-sinif-buraxilis' => '9-klass-vypusknoy',
        '11-ci-sinif-buraxilis' => '11-klass-vypusknoy',
        'elave-tedris-dili' => 'dopolnitelnyy-yazyk',
        '9-illik' => '9-letnee',
        '11-illik' => '11-letnee',
        'azerbaycan-dili-dovlet-dili' => 'azerbaydzhanskiy-gosudarstvennyy',

        // Abituriyent
        '1-ci-merhele' => '1-y-etap',
        '1-ci-qrup' => '1-ya-gruppa',
        '2-ci-qrup' => '2-ya-gruppa',
        '3-cu-qrup' => '3-ya-gruppa',
        '4-cu-qrup' => '4-ya-gruppa',
        '5-ci-qrup' => '5-ya-gruppa',
        'kollec' => 'kolledzh',

        // Magistratura
        'fenn-bloklari' => 'bloki-predmetov',
        'mentiq' => 'logika',
        'xarici-dil' => 'inostrannyy-yazyk',
        'tam-sinaq' => 'polnyy-probnyy',

        // Dövlət qulluğu
        'fealiyyetin-davam-etdirilmesi' => 'prodolzhenie-deyatelnosti',

        // Müəllimlər
        'sertifikasiya' => 'sertifikatsiya',
        'diaqnostik-qiymetlendirme' => 'diagnosticheskaya-otsenka',
        'mektebeqeder' => 'doshkolnoe',

        // Sürücülük
        'kateqoriyalar' => 'kategorii',
        'movzu-testleri' => 'testy-po-temam',
        'biletler' => 'bilety',

        // Digər
        'doktorantura-xarici-dil' => 'doktorantura-inostrannyy',
        'beynelxalq-imtahanlar' => 'mezhdunarodnye-ekzameny',
        'huquq' => 'yurisprudentsiya',
        'olimpiadalar' => 'olimpiady',
    ];

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
                'ru_enabled' => true,
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
                        // Rus sektorunun fənni: imtahanları yalnız ru sektorunda olur
                        'slug' => 'azerbaycan-dili-dovlet-dili',
                        'name' => 'Azərbaycan dili (dövlət dili kimi)',
                        'is_active' => true,
                        'translations' => ['ru' => ['name' => 'Азербайджанский язык (как государственный)']],
                        'description' => 'Rus sektoru üçün. İmtahanlar ru sektorunda açılır.',
                        'subjects' => ['ru' => ['azerbaycan-dili' => []]],
                    ],
                ],
            ],
            [
                'slug' => 'abituriyent', 'name' => 'Abituriyent',
                'short' => 'Bakalavr qəbulu: I mərhələ və I–V qruplar',
                'ru_enabled' => true,
                'translations' => ['ru' => ['name' => 'Абитуриент', 'short' => 'Вступительный экзамен, группы I–V']],
                'children' => [
                    ['slug' => '1-ci-merhele', 'name' => 'I mərhələ', 'group' => 'I-MERHELE',
                        // Ana dili sektora görə dəyişir; xarici dil imtahanı hər dil üçün
                        // ayrıca yaradılır (generasiyada tək seçim).
                        'subjects' => [
                            'az' => ['azerbaycan-dili' => [], 'riyaziyyat' => [],
                                'ingilis-dili' => [], 'rus-dili' => [], 'fransiz-dili' => [], 'alman-dili' => []],
                            'ru' => ['rus-dili' => [], 'riyaziyyat' => [], 'azerbaycan-dili' => [],
                                'ingilis-dili' => [], 'fransiz-dili' => [], 'alman-dili' => []],
                        ]],
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
                        // Ana dili sektora görə: az → Azərbaycan dili, ru → Rus dili (bal eynidir)
                        'slug' => '3-cu-qrup', 'name' => 'III qrup', 'group' => 'III',
                        'subjects' => [
                            'az' => ['azerbaycan-dili' => [], 'edebiyyat' => [], 'cografiya' => [], 'tarix' => []],
                            'ru' => ['rus-dili' => [], 'edebiyyat' => [], 'cografiya' => [], 'tarix' => []],
                        ],
                        'children' => [
                            ['slug' => 'dt', 'name' => 'DT altqrupu', 'group' => 'III-DT',
                                'subjects' => [
                                    'az' => ['azerbaycan-dili' => [], 'edebiyyat' => [], 'tarix' => []],
                                    'ru' => ['rus-dili' => [], 'edebiyyat' => [], 'tarix' => []],
                                ]],
                            ['slug' => 'tc', 'name' => 'TC altqrupu', 'group' => 'III-TC',
                                'subjects' => [
                                    'az' => ['azerbaycan-dili' => [], 'cografiya' => [], 'tarix' => []],
                                    'ru' => ['rus-dili' => [], 'cografiya' => [], 'tarix' => []],
                                ]],
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
                    ['slug' => 'miq', 'name' => 'MİQ (müəllimlərin işə qəbulu)', 'path' => 'miq', 'ru_path' => 'miq',
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

    private function createTree(array $nodes, ?Category $parent = null, bool $ruEnabled = false): void
    {
        foreach ($nodes as $index => $node) {
            $children = $node['children'] ?? [];
            $subjects = $node['subjects'] ?? [];
            unset($node['children'], $node['subjects']);

            // Rus sektoru bayrağı ağacda aşağı ötürülür: kök açıqdırsa, alt düyünlər də açıqdır
            $node['ru_enabled'] = $node['ru_enabled'] ?? $ruEnabled;

            $category = $this->upsert($node, $parent, $index);

            $this->syncSubjects($category, $subjects);
            $this->createTree($children, $category, $category->ru_enabled);
        }
    }

    private function upsert(array $node, ?Category $parent, int $index): Category
    {
        $groupId = isset($node['group'])
            ? Group::where('code', $node['group'])->value('id')
            : null;

        unset($node['group']);

        $path = $node['path'] ?? trim(($parent?->path ? $parent->path.'/' : '').$node['slug'], '/');

        $ruSlug = self::RU_SLUGS[$node['slug']] ?? $node['slug'];
        $node['ru_path'] = $node['ru_path']
            ?? trim(($parent?->ru_path ? $parent->ru_path.'/' : '').$ruSlug, '/');

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

    /**
     * Kateqoriya ↔ fənn pivotu. İki forma qəbul edir:
     *  - düz siyahı: slug => dəyərlər (hər iki sektor üçün, pivotda sector = null)
     *  - sektora görə: ['az' => [slug => ...], 'ru' => [...]] (ana dili sektora görə dəyişəndə)
     *
     * `sync()` işlədilmir: eyni fənn iki sektorda ayrı sətir olur, həm də unikal indeksdə
     * NULL sektor upsert-lə uyğunlaşmır. Ona görə sətirlər silinib yenidən yazılır.
     *
     * @param  array<string, mixed>  $subjects
     */
    private function syncSubjects(Category $category, array $subjects): void
    {
        if ($subjects === []) {
            return;
        }

        $bySector = $this->isSectorKeyed($subjects) ? $subjects : ['' => $subjects];
        $now = now();
        $rows = [];

        foreach ($bySector as $sector => $list) {
            $ids = Subject::whereIn('slug', array_keys($list))->pluck('id', 'slug');
            $order = 0;

            foreach ($list as $slug => $values) {
                if (! $ids->has($slug)) {
                    continue;
                }

                $rows[] = $values + [
                    'category_id' => $category->id,
                    'subject_id' => $ids->get($slug),
                    'sector' => $sector === '' ? null : $sector,
                    'order' => ++$order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($category, $rows) {
            DB::table('category_subject')->where('category_id', $category->id)->delete();

            if ($rows !== []) {
                DB::table('category_subject')->insert($rows);
            }
        });
    }

    /** @param  array<string, mixed>  $subjects */
    private function isSectorKeyed(array $subjects): bool
    {
        return array_keys($subjects) !== []
            && array_diff(array_keys($subjects), Sector::ALL) === [];
    }
}
