<?php

namespace App\Support;

/**
 * Kateqoriyanın adı sinif səviyyəsi bildirirmi?
 *
 * QAYDA: sinif etiketi (`tags.kind = grade`) YALNIZ kateqoriya adı sinfi göstərməyəndə
 * işlədilir. Əks halda kataloqda eyni məlumat iki dəfə görünür və fərqi anlaşılmır:
 * "9-cu sinif buraxılış" kateqoriyası + "9-cu sinif" etiketi.
 *
 * Etiketin yeri kateqoriyası NEYTRAL olan bölmələrdir — olimpiadalar, liseylərə qəbul,
 * mövzu testləri: orada səviyyəni yalnız etiket bildirir.
 *
 * Yoxlama həm ada, həm də yola baxır: yol valideyn zəncirini də daşıdığı üçün
 * ("mekteb/elave-tedris-dili/9-illik") valideyni sinif bildirən düyün də tutulur.
 */
class GradeMention
{
    /**
     * Mətndə "9-cu sinif", "9 sinif", "11 illik" və ya rusca "9 класс" varmı?
     *
     * Şəkilçi ixtiyaridir: slug-da ("9-cu-sinif-buraxilis") və adda ("9-cu sinif")
     * eyni işləsin. "1-ci mərhələ", "V qrup", "B kateqoriyası" tutulmur.
     */
    public static function inText(?string $text): bool
    {
        if ($text === null || $text === '') {
            return false;
        }

        return (bool) preg_match(
            '/\d+\s*-?\s*(ci|cı|cu|cü)?\s*-?\s*(sinif|illik|класс)/iu',
            $text,
        );
    }

    /** Mətnlərdən heç olmasa biri sinif bildirirsə. */
    public static function inAny(string ...$texts): bool
    {
        foreach ($texts as $text) {
            if (self::inText($text)) {
                return true;
            }
        }

        return false;
    }
}
