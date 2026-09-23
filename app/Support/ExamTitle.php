<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Exam;

/**
 * İmtahanın adı: avtomatik qurulması və "ad heç nə əlavə etmir" yoxlaması.
 *
 * Kataloq kartında başlıq imtahanın ÖZ ADIDIR, altında isə kiçik etiket kimi növ və rüb
 * ("Mövzu sınağı — 2-ci rüb"). Ad avtomatik qurulubsa və kateqoriya + növdən başqa heç nə
 * demirsə, başlıq təkrar olur — belə kartda yalnız növ etiketi qalır.
 */
class ExamTitle
{
    /** Növün adı (kataloqla eyni mətn) */
    public static function kindLabel(string $kind, ?string $locale = null): string
    {
        return (string) __('category_page.kinds.'.$kind, [], $locale ?? app()->getLocale());
    }

    public static function quarterLabel(int $quarter, ?string $locale = null): string
    {
        return (string) __('category_page.quarter', ['number' => $quarter], $locale ?? app()->getLocale());
    }

    /**
     * Avtomatik ad: "II qrup — Mövzu sınağı (2-ci rüb)".
     *
     * Admin generasiya formasında adı boş qoyanda bu işlənir.
     */
    public static function generate(?Category $category, string $kind, ?int $quarter = null): string
    {
        $parts = array_filter([$category?->name, self::kindLabel($kind)]);
        $title = implode(' — ', $parts);

        return $quarter !== null ? $title.' ('.self::quarterLabel($quarter).')' : $title;
    }

    /**
     * Ad kartda göstərilməli deyilmi? (kateqoriya, növ və rübdən başqa heç nə demirsə)
     *
     * Mətndən kateqoriya zəncirinin adları, növün adı və rüb çıxarılır; geridə hərf və ya
     * rəqəm qalmırsa ad "avtomatikdir" sayılır və kart yalnız növ etiketi göstərir.
     */
    public static function isGeneric(Exam $exam): bool
    {
        $rest = AnswerNormalizer::normalizeText((string) $exam->title);

        $noise = array_filter([
            $exam->category?->name,
            $exam->category?->localized('name'),
            $exam->category?->rootAncestor()->name,
            self::kindLabel($exam->kind, 'az'),
            self::kindLabel($exam->kind, 'ru'),
            $exam->quarter ? self::quarterLabel($exam->quarter, 'az') : null,
            $exam->quarter ? self::quarterLabel($exam->quarter, 'ru') : null,
        ]);

        foreach ($noise as $piece) {
            $rest = str_replace(AnswerNormalizer::normalizeText((string) $piece), ' ', $rest);
        }

        // Qalan durğu işarələri ad sayılmır: "— :" kimi qırıntılar mənasızdır
        return preg_match('/[\p{L}\p{N}]/u', $rest) !== 1;
    }
}
