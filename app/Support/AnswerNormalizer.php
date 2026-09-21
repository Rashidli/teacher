<?php

namespace App\Support;

/**
 * open_coded (qısa/rəqəm cavablı) sualların yoxlanması.
 *
 * Şagirdin cavabı da, qəbul olunan cavab da eyni qaydada normallaşdırılır və mümkün olduqda
 * ƏDƏDİ müqayisə edilir — sətir kimi deyil. Beləliklə admin "0,5" yazsa da şagirdin
 * "0.5", ".5", "1/2" cavabları qəbul olunur və hər variantı ayrıca yazmaq lazım gəlmir.
 *
 * Ədədə çevrilməyən cavablar ("x=2" kimi) normallaşdırılmış mətn kimi müqayisə olunur.
 */
class AnswerNormalizer
{
    /** Sürüşən vergül xətası üçün nisbi tolerantlıq */
    public const TOLERANCE = 1e-9;

    /**
     * Şagirdin cavabı qəbul olunan cavablardan heç olmasa birinə uyğundurmu?
     *
     * @param  array<int, string|int|float|null>  $accepted
     */
    public static function matches(?string $given, array $accepted): bool
    {
        if ($given === null || trim($given) === '') {
            return false;
        }

        foreach ($accepted as $candidate) {
            if ($candidate === null || trim((string) $candidate) === '') {
                continue;
            }

            if (self::equals($given, (string) $candidate)) {
                return true;
            }
        }

        return false;
    }

    /** İki cavab eynidirmi: əvvəlcə ədədi, mümkün deyilsə mətn müqayisəsi. */
    public static function equals(string $a, string $b): bool
    {
        $numberA = self::toNumber($a);
        $numberB = self::toNumber($b);

        if ($numberA !== null && $numberB !== null) {
            $scale = max(1.0, abs($numberA), abs($numberB));

            return abs($numberA - $numberB) <= self::TOLERANCE * $scale;
        }

        return self::normalizeText($a) === self::normalizeText($b);
    }

    /**
     * "0,5" → 0.5 | "1/2" → 0.5 | " -3 " → -3.0 | "2·10^3" → null (ədəd deyil)
     */
    public static function toNumber(string $value): ?float
    {
        $value = self::cleanNumeric($value);

        if ($value === '') {
            return null;
        }

        // Adi kəsr: 1/2, -3/4, 2.5/5
        if (preg_match('#^(-?\d+(?:\.\d+)?)/(-?\d+(?:\.\d+)?)$#', $value, $matches)) {
            $denominator = (float) $matches[2];

            return $denominator == 0.0 ? null : ((float) $matches[1]) / $denominator;
        }

        // Onluq və tam ədədlər: 5, -3, 0.5, .5
        if (preg_match('/^-?(?:\d+(?:\.\d+)?|\.\d+)$/', $value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * Mətn cavabları: kənar boşluqlar atılır, daxili boşluqlar birləşir, kiçik hərfə salınır.
     *
     * Azərbaycan əlifbasında "İ/i" (nöqtəli) və "I/ı" (nöqtəsiz) ayrı hərflərdir. mb_strtolower
     * "İ"-ni birləşən nöqtə ilə "i̇" edir və müqayisə pozulur, ona görə bu iki hərf əvvəlcədən
     * öz qarşılığına çevrilir.
     */
    public static function normalizeText(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value));
        $value = str_replace(['İ', 'I'], ['i', 'ı'], $value);

        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Ədədi müqayisəyə hazırlıq: bütün boşluqlar atılır, vergül nöqtəyə çevrilir,
     * Unicode minus/tire adi "-" ilə əvəzlənir.
     */
    private static function cleanNumeric(string $value): string
    {
        $value = str_replace(["\u{2212}", "\u{2013}", "\u{2014}"], '-', trim($value));
        $value = preg_replace('/\s+/u', '', $value);

        return str_replace(',', '.', $value);
    }
}
