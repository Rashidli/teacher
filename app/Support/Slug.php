<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Azərbaycan mətnindən URL slug-ı.
 *
 * Hərf xəritəsi AÇIQ yazılıb: `Str::slug()` hazırda eyni nəticəni verir, amma onun daxili
 * transliterasiya cədvəli Laravel versiyaları arasında dəyişə bilər (məs. `ö` → `oe`).
 * Kateqoriya slug-ları ilə eyni qayda: ə→e, ı→i, ö→o, ü→u, ş→s, ç→c, ğ→g.
 */
class Slug
{
    /** @var array<string, string> */
    private const AZ_MAP = [
        'ə' => 'e', 'Ə' => 'e',
        'ı' => 'i', 'I' => 'i',
        'i' => 'i', 'İ' => 'i',
        'ö' => 'o', 'Ö' => 'o',
        'ü' => 'u', 'Ü' => 'u',
        'ş' => 's', 'Ş' => 's',
        'ç' => 'c', 'Ç' => 'c',
        'ğ' => 'g', 'Ğ' => 'g',
    ];

    public static function make(string $value): string
    {
        return Str::slug(strtr($value, self::AZ_MAP));
    }

    /**
     * Cədvəldə unikal slug: təkrar olanda "-2", "-3" ... əlavə olunur.
     *
     * @param  callable(string): bool  $exists  slug artıq işlənibmi
     */
    public static function unique(string $value, callable $exists, string $fallback = 'imtahan'): string
    {
        $base = self::make($value);

        // Başlıq tamamilə latın hərfsizdirsə (məs. yalnız durğu işarəsi) slug boş qalır
        if ($base === '') {
            $base = $fallback;
        }

        $slug = $base;
        $suffix = 1;

        while ($exists($slug)) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
