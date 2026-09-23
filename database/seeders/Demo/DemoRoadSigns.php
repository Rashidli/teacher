<?php

namespace Database\Seeders\Demo;

/**
 * Sürücülük sualları üçün nümunə yol nişanları — SVG olaraq burada generasiya olunur.
 *
 * Xarici şəkil yüklənmir: fayllar deterministik SVG mətnindən qurulur, ona görə seeder
 * təkrar işlədiləndə eyni fayl yazılır və `demo:clear` onları qovluqla birlikdə silir.
 *
 * DİQQƏT: nişanın ÜZƏRİNDƏ adı yazılmır — əks halda sual öz cavabını verərdi. Yalnız
 * standart forma, rəng və piktoqram çəkilir; `alt` mətni də formanı təsvir edir, mənasını
 * yox ("üçbucaq nişan, içində əyri ox" — "təhlükəli döngə" deyil).
 */
class DemoRoadSigns
{
    /** Fayllar bu qovluğa yazılır (`storage/app/public/` altında) */
    public const DIRECTORY = 'questions/demo-road-signs';

    /**
     * Nişan kataloqu.
     *
     * `meaning` — düzgün cavab, `group` — nişan qrupu (ikinci sual çərçivəsi üçün),
     * `alt` — ekran oxuyucusu üçün formanın təsviri.
     *
     * @return array<int, array{key: string, meaning: string, group: string, alt: string, svg: string}>
     */
    public static function all(): array
    {
        return [
            self::sign('giris-qadagan', 'Giriş qadağandır', 'Qadağan nişanı',
                'Dairəvi nişan: qırmızı fon, ortasında geniş ağ üfüqi zolaq',
                self::circle('#C8354E', '<rect x="22" y="44" width="56" height="12" rx="2" fill="#fff"/>')),

            self::sign('suret-50', 'Sürət həddi 50 km/saat', 'Qadağan nişanı',
                'Dairəvi nişan: ağ fon, qalın qırmızı halqa, ortasında "50" rəqəmi',
                self::ring('<text x="50" y="62" text-anchor="middle" font-family="Arial, sans-serif" font-size="36" font-weight="700" fill="#1E2227">50</text>')),

            self::sign('suret-90', 'Sürət həddi 90 km/saat', 'Qadağan nişanı',
                'Dairəvi nişan: ağ fon, qalın qırmızı halqa, ortasında "90" rəqəmi',
                self::ring('<text x="50" y="62" text-anchor="middle" font-family="Arial, sans-serif" font-size="36" font-weight="700" fill="#1E2227">90</text>')),

            self::sign('otmek-qadagan', 'Ötmək qadağandır', 'Qadağan nişanı',
                'Dairəvi nişan: ağ fon, qırmızı halqa, içində yan-yana iki avtomobil — soldakı qırmızı',
                self::ring(
                    '<rect x="26" y="40" width="20" height="26" rx="4" fill="#C8354E"/>'
                    .'<rect x="30" y="44" width="12" height="8" rx="2" fill="#fff"/>'
                    .'<rect x="54" y="40" width="20" height="26" rx="4" fill="#1E2227"/>'
                    .'<rect x="58" y="44" width="12" height="8" rx="2" fill="#fff"/>'
                )),

            self::sign('duz-hereket', 'Yalnız düz hərəkət', 'Məcburi hərəkət nişanı',
                'Dairəvi mavi nişan: içində yuxarı yönəlmiş ağ ox',
                self::circle('#2440A0', '<path d="M50 74V34M50 30l-14 14M50 30l14 14" fill="none" stroke="#fff" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>')),

            self::sign('saga-hereket', 'Yalnız sağa hərəkət', 'Məcburi hərəkət nişanı',
                'Dairəvi mavi nişan: içində sağa yönəlmiş ağ ox',
                self::circle('#2440A0', '<path d="M28 50h42M74 50l-14-14M74 50l-14 14" fill="none" stroke="#fff" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>')),

            self::sign('dairevi-hereket', 'Dairəvi hərəkət', 'Məcburi hərəkət nişanı',
                'Dairəvi mavi nişan: içində dairə üzrə düzülmüş üç ağ ox',
                self::circle('#2440A0',
                    '<path d="M50 26a24 24 0 1 1-17 41" fill="none" stroke="#fff" stroke-width="8" stroke-linecap="round"/>'
                    .'<path d="M50 18l12 8-12 8z" fill="#fff"/>'
                    .'<path d="M28 58l4 14 12-8z" fill="#fff"/>'
                )),

            self::sign('dayanacaq', 'Dayanacaq yeri', 'Məlumatverici nişan',
                'Kvadrat mavi nişan: ortasında iri ağ "P" hərfi',
                self::square('#2440A0', '<text x="50" y="68" text-anchor="middle" font-family="Arial, sans-serif" font-size="52" font-weight="700" fill="#fff">P</text>')),

            self::sign('piyada-kecidi', 'Piyada keçidi', 'Məlumatverici nişan',
                'Kvadrat mavi nişan: içində ağ üçbucaq, üçbucaqda addımlayan insan fiquru və zolaqlar',
                self::square('#2440A0',
                    '<path d="M50 20L84 80H16z" fill="#fff"/>'
                    .'<circle cx="46" cy="46" r="5" fill="#1E2227"/>'
                    .'<path d="M46 52v12M46 56l-7 5M46 56l7 4M46 64l-6 9M46 64l6 9" stroke="#1E2227" stroke-width="3.5" stroke-linecap="round" fill="none"/>'
                    .'<path d="M30 76h40" stroke="#1E2227" stroke-width="3" stroke-dasharray="5 4"/>'
                )),

            self::sign('tehlukeli-donge', 'Təhlükəli döngə (sağa)', 'Xəbərdarlıq nişanı',
                'Üçbucaq nişan: ağ fon, qırmızı kənar, içində sağa əyilən qara ox',
                self::triangle('<path d="M43 72V56c0-10 8-14 14-14M57 42l-8-7M57 42l-8 7" fill="none" stroke="#1E2227" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>')),

            self::sign('yol-isleri', 'Yol işləri', 'Xəbərdarlıq nişanı',
                'Üçbucaq nişan: ağ fon, qırmızı kənar, içində bel tutan işçi fiquru',
                self::triangle(
                    '<circle cx="46" cy="42" r="5" fill="#1E2227"/>'
                    .'<path d="M46 48v12M46 52l10 6M40 72l6-12 6 12" stroke="#1E2227" stroke-width="4.5" stroke-linecap="round" fill="none"/>'
                    .'<path d="M56 58l8-8" stroke="#1E2227" stroke-width="4.5" stroke-linecap="round"/>'
                    .'<path d="M62 44l8 8-6 6-8-8z" fill="#1E2227"/>'
                )),

            self::sign('yol-verin', 'Yol verin', 'Üstünlük nişanı',
                'Tərsinə çevrilmiş üçbucaq: ağ fon, qalın qırmızı kənar, içi boş',
                self::invertedTriangle()),
        ];
    }

    /**
     * Yolayrıcı sxemləri: "kim birinci keçir" sualları üçün.
     *
     * @return array<int, array{key: string, meaning: string, group: string, alt: string, svg: string}>
     */
    public static function junctions(): array
    {
        return [
            self::sign('yolayrici-saginda', 'Sağdan gələn avtomobil', 'Yolayrıcı sxemi',
                'Sxem: bərabərhüquqlu yolayrıcı, aşağıdan mavi, sağdan qırmızı avtomobil yaxınlaşır',
                self::junction(bottom: true, right: true)),

            self::sign('yolayrici-duz', 'Düz gedən avtomobil', 'Yolayrıcı sxemi',
                'Sxem: yolayrıcı, aşağıdan mavi avtomobil düz gedir, qarşıdan qırmızı avtomobil sola dönür',
                self::junction(bottom: true, top: true)),

            self::sign('yolayrici-solda', 'Aşağıdan gələn avtomobil', 'Yolayrıcı sxemi',
                'Sxem: bərabərhüquqlu yolayrıcı, aşağıdan mavi, soldan qırmızı avtomobil yaxınlaşır',
                self::junction(bottom: true, left: true)),

            self::sign('yolayrici-esas-yol', 'Əsas yolda gedən avtomobil', 'Yolayrıcı sxemi',
                'Sxem: üfüqi yol qalın (əsas yol), şaquli yol nazik; hər iki yolda bir avtomobil',
                self::junction(bottom: true, right: true, mainRoad: true)),
        ];
    }

    /* ------------------------------------------------------------ SVG qəlibləri */

    /** @return array{key: string, meaning: string, group: string, alt: string, svg: string} */
    private static function sign(string $key, string $meaning, string $group, string $alt, string $body): array
    {
        return [
            'key' => $key,
            'meaning' => $meaning,
            'group' => $group,
            'alt' => $alt,
            'svg' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="160" height="160" role="img">'
                .$body
                .'</svg>',
        ];
    }

    /** Dolu dairə (qadağan/məcburi nişanlar) */
    private static function circle(string $fill, string $inner): string
    {
        return '<circle cx="50" cy="50" r="46" fill="'.$fill.'" stroke="#fff" stroke-width="4"/>'.$inner;
    }

    /** Ağ dairə + qalın qırmızı halqa (sürət həddi, ötmə qadağası) */
    private static function ring(string $inner): string
    {
        return '<circle cx="50" cy="50" r="46" fill="#fff"/>'
            .'<circle cx="50" cy="50" r="39" fill="none" stroke="#C8354E" stroke-width="12"/>'
            .$inner;
    }

    /** Mavi kvadrat (məlumatverici nişanlar) */
    private static function square(string $fill, string $inner): string
    {
        return '<rect x="6" y="6" width="88" height="88" rx="6" fill="'.$fill.'" stroke="#fff" stroke-width="4"/>'.$inner;
    }

    /** Qırmızı kənarlı üçbucaq (xəbərdarlıq nişanları) */
    private static function triangle(string $inner): string
    {
        return '<path d="M50 6L96 90H4z" fill="#fff" stroke="#C8354E" stroke-width="9" stroke-linejoin="round"/>'.$inner;
    }

    /** "Yol verin" — tərsinə üçbucaq */
    private static function invertedTriangle(): string
    {
        return '<path d="M50 94L4 10h92z" fill="#fff" stroke="#C8354E" stroke-width="11" stroke-linejoin="round"/>';
    }

    /**
     * Yolayrıcı sxemi: boz yollar + göstərilən istiqamətlərdən yaxınlaşan avtomobillər.
     * Mavi avtomobil "bizim" nəqliyyat vasitəsidir, qırmızılar qarşı tərəfdir.
     */
    private static function junction(
        bool $bottom = false,
        bool $top = false,
        bool $left = false,
        bool $right = false,
        bool $mainRoad = false,
    ): string {
        $vertical = $mainRoad ? 20 : 28;

        $svg = '<rect x="0" y="0" width="100" height="100" fill="#F3F4F1"/>'
            .'<rect x="'.((100 - $vertical) / 2).'" y="0" width="'.$vertical.'" height="100" fill="#C9CCC7"/>'
            .'<rect x="0" y="36" width="100" height="28" fill="#C9CCC7"/>';

        if ($bottom) {
            $svg .= self::car(44, 74, '#2440A0');
        }

        if ($top) {
            $svg .= self::car(44, 8, '#C8354E');
        }

        if ($right) {
            $svg .= self::car(76, 42, '#C8354E', horizontal: true);
        }

        if ($left) {
            $svg .= self::car(6, 42, '#C8354E', horizontal: true);
        }

        return $svg;
    }

    private static function car(float $x, float $y, string $fill, bool $horizontal = false): string
    {
        return $horizontal
            ? '<rect x="'.$x.'" y="'.$y.'" width="18" height="12" rx="3" fill="'.$fill.'"/>'
                .'<rect x="'.($x + 4).'" y="'.($y + 2).'" width="7" height="8" rx="2" fill="#fff" opacity="0.85"/>'
            : '<rect x="'.$x.'" y="'.$y.'" width="12" height="18" rx="3" fill="'.$fill.'"/>'
                .'<rect x="'.($x + 2).'" y="'.($y + 4).'" width="8" height="7" rx="2" fill="#fff" opacity="0.85"/>';
    }
}
