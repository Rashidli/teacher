<?php

namespace Database\Seeders\Demo;

use App\Models\Exam;

/**
 * Hansı kateqoriyada hansı imtahanlar qurulur.
 *
 * Yalnız YARPAQ düyünlər siyahıdadır: kateqoriya səhifəsi `subtreeIds()` ilə işlədiyi üçün
 * valideyn düyün övladlarının imtahanlarını onsuz da göstərir. Ona görə "Abituriyent" və ya
 * "Sürücülük vəsiqəsi" kimi toplayıcı düyünlər öz imtahanını almır — kataloq yenə dolu olur.
 * İstisna: I və III qrup öz imtahanını alır (DİM-də altqrupsuz, bütöv qrup imtahanı da var).
 *
 * Hər düyündə 4 imtahan qurulur ki, kataloqun NÖV filtrində dörd variantın hamısı olsun:
 *   ümumi sınaq (pulsuz) · mövzu sınağı (pullu) · fənn sınağı (pulsuz) · məşq testi (pullu)
 * Beləcə QİYMƏT filtri də hər düyündə hər iki variantı göstərir.
 *
 * Mövzu sınağının rübü düyündən-düyünə dəyişir (`quarter_seed`): valideyn səhifədə
 * övladların rübləri toplanır və rüb seçimi dörd variantı da göstərir.
 *
 * `kinds` — düyündə hansı növlər qurulur (defolt: dördü də).
 *
 * `practice_grades` — MƏŞQ TESTİNƏ bağlanan sinif səviyyəsi etiketləri.
 * Sinif etiketi yalnız kateqoriya adı sinfi GÖSTƏRMƏYƏNDƏ işlədilir: "9-cu sinif buraxılış"
 * bölməsində etiket təkrar olardı, ona görə buraxılış, abituriyent, magistratura, dövlət
 * qulluğu, MİQ və sürücülük düyünlərində sinif etiketi YOXDUR. Nümunə üçün yeganə neytral
 * adlı məktəb düyünü ("Orta məktəb") ibtidai və orta sinif məşq testləri alır —
 * kataloqun "Sinif" filtri boş qalmasın. Bax `App\Support\GradeMention`.
 *
 * `sets` — imtahanın bölmələri (fənn slug-ları). Bir neçə dəst verilərsə, imtahanlar onları
 * növbə ilə işlədir: MİQ və sertifikasiyada bu, "müəllimin öz fənni + Kurikulum və metodika"
 * quruluşunu hər imtahanda başqa fənnlə göstərir.
 */
class DemoCatalog
{
    /** Hər düyündə neçə imtahan qurulur (hər sektor üçün ayrıca) */
    public const EXAMS_PER_NODE = 4;

    /** İmtahan növlərinin növbəsi — dörd variantın hamısı hər düyündə olur */
    public const KINDS = [
        Exam::KIND_GENERAL,
        Exam::KIND_TOPIC_TRIAL,
        Exam::KIND_SUBJECT,
        Exam::KIND_PRACTICE,
    ];

    /** Ödənişli imtahanların qiymət növbəsi (AZN) */
    public const PRICES = [3, 4.5, 5, 6, 7.5, 8, 9, 10];

    /**
     * @return array<int, array{
     *     path: string, label: string, duration: int, options: int,
     *     sets: array<int, array<int, string>>, ru_sets?: array<int, array<int, string>>,
     *     az?: bool
     * }>
     */
    public static function nodes(): array
    {
        return [
            /* ---------------------------------------------------------- Orta məktəb */
            [
                /*
                 * SİNİF ETİKETİNİN NÜMUNƏSİ. Adında sinif yoxdur ("Orta məktəb"), ona görə
                 * səviyyəni yalnız etiket bildirir — kataloqda "Sinif" filtri məhz belə
                 * imtahanlar üçündür. Valideyn düyündür: alt düyünlərin imtahanları onsuz
                 * da burada görünür, əlavə olunan yalnız üç məşq testidir.
                 */
                'path' => 'mekteb', 'label' => 'Orta məktəb',
                'duration' => 60, 'options' => 4,
                'kinds' => [Exam::KIND_PRACTICE],
                // ibtidai (4-cü) və orta (6-cı, 8-ci) siniflər
                'practice_grades' => [4, 6, 8],
                'sets' => [['riyaziyyat']],
                'ru_sets' => [['riyaziyyat']],
            ],
            [
                'path' => 'mekteb/9-cu-sinif-buraxilis', 'label' => '9-cu sinif buraxılış',
                'duration' => 120, 'options' => 5,
                'sets' => [['azerbaycan-dili', 'riyaziyyat', 'ingilis-dili']],
                'ru_sets' => [['rus-dili', 'riyaziyyat', 'azerbaycan-dili']],
            ],
            [
                'path' => 'mekteb/11-ci-sinif-buraxilis', 'label' => '11-ci sinif buraxılış',
                'duration' => 150, 'options' => 5,
                'sets' => [['azerbaycan-dili', 'riyaziyyat', 'tarix']],
                'ru_sets' => [['rus-dili', 'riyaziyyat', 'tarix']],
            ],
            [
                'path' => 'mekteb/elave-tedris-dili/9-illik', 'label' => 'Əlavə tədris dili (9 illik)',
                'duration' => 90, 'options' => 4,
                'sets' => [['ingilis-dili', 'rus-dili']],
                'ru_sets' => [['ingilis-dili', 'azerbaycan-dili']],
            ],
            [
                'path' => 'mekteb/elave-tedris-dili/11-illik', 'label' => 'Əlavə tədris dili (11 illik)',
                'duration' => 90, 'options' => 4,
                'sets' => [['ingilis-dili', 'rus-dili']],
                'ru_sets' => [['ingilis-dili', 'azerbaycan-dili']],
            ],
            [
                // Rus sektorunun fənni: az sektorunda imtahanı olmur
                'path' => 'mekteb/azerbaycan-dili-dovlet-dili', 'label' => 'Azərbaycan dili (dövlət dili)',
                'duration' => 90, 'options' => 5, 'az' => false,
                'sets' => [], 'ru_sets' => [['azerbaycan-dili']],
            ],

            /* ----------------------------------------------------------- Abituriyent */
            [
                'path' => 'abituriyent/1-ci-merhele', 'label' => 'Abituriyent I mərhələ',
                'duration' => 150, 'options' => 5,
                'sets' => [['azerbaycan-dili', 'riyaziyyat', 'ingilis-dili']],
                'ru_sets' => [['rus-dili', 'riyaziyyat', 'ingilis-dili']],
            ],
            [
                'path' => 'abituriyent/1-ci-qrup', 'label' => 'Abituriyent I qrup',
                'duration' => 180, 'options' => 5,
                'sets' => [['riyaziyyat', 'fizika', 'kimya']],
                'ru_sets' => [['riyaziyyat', 'fizika', 'kimya']],
            ],
            [
                'path' => 'abituriyent/1-ci-qrup/rk', 'label' => 'I qrup — RK altqrupu',
                'duration' => 180, 'options' => 5,
                'sets' => [['riyaziyyat', 'fizika', 'kimya']],
                'ru_sets' => [['riyaziyyat', 'fizika', 'kimya']],
            ],
            [
                'path' => 'abituriyent/1-ci-qrup/ri', 'label' => 'I qrup — Rİ altqrupu',
                'duration' => 180, 'options' => 5,
                'sets' => [['riyaziyyat', 'fizika', 'informatika']],
                'ru_sets' => [['riyaziyyat', 'fizika', 'informatika']],
            ],
            [
                'path' => 'abituriyent/2-ci-qrup', 'label' => 'Abituriyent II qrup',
                'duration' => 180, 'options' => 5,
                'sets' => [['riyaziyyat', 'tarix', 'cografiya']],
                'ru_sets' => [['riyaziyyat', 'tarix', 'cografiya']],
            ],
            [
                'path' => 'abituriyent/3-cu-qrup', 'label' => 'Abituriyent III qrup',
                'duration' => 180, 'options' => 5,
                'sets' => [['azerbaycan-dili', 'edebiyyat', 'tarix']],
                'ru_sets' => [['rus-dili', 'edebiyyat', 'tarix']],
            ],
            [
                'path' => 'abituriyent/3-cu-qrup/dt', 'label' => 'III qrup — DT altqrupu',
                'duration' => 180, 'options' => 5,
                'sets' => [['azerbaycan-dili', 'edebiyyat', 'tarix']],
                'ru_sets' => [['rus-dili', 'edebiyyat', 'tarix']],
            ],
            [
                'path' => 'abituriyent/3-cu-qrup/tc', 'label' => 'III qrup — TC altqrupu',
                'duration' => 180, 'options' => 5,
                'sets' => [['azerbaycan-dili', 'cografiya', 'tarix']],
                'ru_sets' => [['rus-dili', 'cografiya', 'tarix']],
            ],
            [
                'path' => 'abituriyent/4-cu-qrup', 'label' => 'Abituriyent IV qrup',
                'duration' => 180, 'options' => 5,
                'sets' => [['fizika', 'kimya', 'biologiya']],
                'ru_sets' => [['fizika', 'kimya', 'biologiya']],
            ],
            [
                'path' => 'abituriyent/kollec', 'label' => 'Kollec qəbulu',
                'duration' => 120, 'options' => 4,
                'sets' => [['azerbaycan-dili', 'riyaziyyat']],
                'ru_sets' => [['rus-dili', 'riyaziyyat']],
            ],

            /* ---------------------------------------------------------- Magistratura */
            [
                'path' => 'magistratura/fenn-bloklari/mentiq', 'label' => 'Magistratura — Məntiq',
                'duration' => 90, 'options' => 5, 'sets' => [['mentiq']],
            ],
            [
                'path' => 'magistratura/fenn-bloklari/informatika', 'label' => 'Magistratura — İnformatika',
                'duration' => 90, 'options' => 5, 'sets' => [['informatika']],
            ],
            [
                'path' => 'magistratura/fenn-bloklari/xarici-dil', 'label' => 'Magistratura — Xarici dil',
                'duration' => 90, 'options' => 5, 'sets' => [['ingilis-dili']],
            ],
            [
                'path' => 'magistratura/tam-sinaq', 'label' => 'Magistratura tam sınaq',
                'duration' => 180, 'options' => 5,
                'sets' => [['mentiq', 'informatika', 'ingilis-dili']],
            ],

            /* -------------------------------------------------------- Dövlət qulluğu */
            [
                'path' => 'dovlet-qullugu/tam-sinaq/bb-ac', 'label' => 'Dövlət qulluğu — BB və AC',
                'duration' => 150, 'options' => 5, 'sets' => [['qanunvericilik', 'mentiq']],
            ],
            [
                'path' => 'dovlet-qullugu/tam-sinaq/ba-ab', 'label' => 'Dövlət qulluğu — BA və AB',
                'duration' => 180, 'options' => 5,
                'sets' => [['qanunvericilik', 'mentiq', 'azerbaycan-dili']],
            ],
            [
                'path' => 'dovlet-qullugu/tam-sinaq/aa', 'label' => 'Dövlət qulluğu — AA',
                'duration' => 180, 'options' => 5,
                'sets' => [['qanunvericilik', 'mentiq', 'informatika']],
            ],
            [
                'path' => 'dovlet-qullugu/fealiyyetin-davam-etdirilmesi',
                'label' => 'Fəaliyyətin davam etdirilməsi',
                'duration' => 120, 'options' => 5, 'sets' => [['qanunvericilik']],
            ],

            /* ------------------------------------------------------------ Müəllimlər */
            [
                // İkiqat quruluş: müəllimin öz fənni + Kurikulum və metodika.
                // Hər imtahan başqa fənn bloku ilə qurulur.
                'path' => 'miq', 'label' => 'MİQ',
                'duration' => 150, 'options' => 5,
                'sets' => [
                    ['riyaziyyat', 'kurikulum-ve-metodika'],
                    ['azerbaycan-dili', 'kurikulum-ve-metodika'],
                    ['tarix', 'kurikulum-ve-metodika'],
                    ['ingilis-dili', 'kurikulum-ve-metodika'],
                ],
            ],
            [
                'path' => 'muellimler/sertifikasiya', 'label' => 'Sertifikasiya',
                'duration' => 150, 'options' => 5,
                'sets' => [
                    ['biologiya', 'kurikulum-ve-metodika'],
                    ['edebiyyat', 'kurikulum-ve-metodika'],
                    ['fizika', 'kurikulum-ve-metodika'],
                    ['cografiya', 'kurikulum-ve-metodika'],
                ],
            ],
            [
                'path' => 'muellimler/diaqnostik-qiymetlendirme', 'label' => 'Diaqnostik qiymətləndirmə',
                'duration' => 120, 'options' => 5,
                'sets' => [
                    ['riyaziyyat', 'kurikulum-ve-metodika'],
                    ['informatika', 'kurikulum-ve-metodika'],
                    ['azerbaycan-dili', 'kurikulum-ve-metodika'],
                    ['kimya', 'kurikulum-ve-metodika'],
                ],
            ],
            [
                'path' => 'muellimler/mektebeqeder', 'label' => 'Məktəbəqədər',
                'duration' => 90, 'options' => 4, 'sets' => [['kurikulum-ve-metodika']],
            ],

            /* -------------------------------------------------------------- Sürücülük */
            [
                'path' => 'suruculuk-imtahani/biletler', 'label' => 'Sürücülük — imtahan biletləri',
                'duration' => 30, 'options' => 4, 'sets' => [['yol-hereketi-qaydalari']],
            ],
            [
                'path' => 'suruculuk-imtahani/movzu-testleri', 'label' => 'Sürücülük — mövzu testləri',
                'duration' => 30, 'options' => 4, 'sets' => [['yol-hereketi-qaydalari']],
            ],
            ...self::drivingCategories(),
        ];
    }

    /** Sürücülük kateqoriyaları (A, B, C …) — hamısında eyni nəzəri fənn */
    private static function drivingCategories(): array
    {
        return array_map(fn (string $code) => [
            'path' => 'suruculuk-imtahani/kateqoriyalar/'.mb_strtolower($code),
            'label' => 'Sürücülük — '.$code.' kateqoriyası',
            'duration' => 30,
            'options' => 4,
            'sets' => [['yol-hereketi-qaydalari']],
        ], ['A', 'B', 'C', 'D', 'BE', 'CE', 'DE']);
    }
}
