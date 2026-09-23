<?php

namespace App\Support;

use App\Models\Question;

/**
 * DİM-in KODLAŞDIRILAN açıq tapşırıqlarının yoxlanması.
 *
 * Dörd alt növ var və hamısı AVTOMATİK yoxlanılır (xam dəyəri 1 bal):
 *
 *  - `numeric`      — hesablama: rəqəm/qısa mətn, `AnswerNormalizer` ilə;
 *  - `multi_select` — bir neçə düzgün variant; kod: hərflər əlifba sırası ilə, "A,C";
 *  - `ordering`     — variantlar düzgün ardıcıllıqla; kod: hərflər ŞAGİRDİN sırası ilə, "C,A,B";
 *  - `matching`     — sol-sağ uyğunluq; kod: "1-2,2-1,3-3" (sol bəndin nömrəsi - sağ bəndin nömrəsi).
 *
 * KOD HƏRFLƏ İŞLƏYİR, id ilə yox: sual kopyalananda variantların id-ləri dəyişir, hərflər
 * isə qalır — köhnə cəhdlərin cavabı oxunaqlı olaraq qalır.
 *
 * DÜZGÜN CAVAB SAXLANILMIR, hesablanır: seçimdə `is_correct` variantlardan, ardıcıllıqda
 * variantların `order` sırasından, uyğunluqda isə cütlərin öz sırasından. Beləcə admin
 * variantı düzəldəndə "düzgün cavab" köhnəlmir.
 */
class CodedAnswer
{
    public const SEPARATOR = ',';

    public const PAIR_SEPARATOR = '-';

    /**
     * Şagirdin cavabı düzgündürmü?
     *
     * Boş cavab həmişə yanlışdır — cavabsız sual da bu yolla keçir.
     */
    public static function matches(Question $question, ?string $given): bool
    {
        if ($given === null || trim($given) === '') {
            return false;
        }

        if ($question->codedSubtype() === Question::CODED_NUMERIC) {
            return AnswerNormalizer::matches($given, (array) ($question->accepted_answers ?? []));
        }

        $correct = self::correct($question);

        return $correct !== null && self::normalize($question, $given) === $correct;
    }

    /**
     * Düzgün cavabın kodu. Hesablama (numeric) sualında kod yoxdur — orada
     * `accepted_answers` işlənir, ona görə null qaytarılır.
     */
    public static function correct(Question $question): ?string
    {
        return match ($question->codedSubtype()) {
            Question::CODED_MULTI_SELECT => self::join(
                $question->options->where('is_correct', true)
                    ->pluck('option_letter')->map(fn ($letter) => self::letter($letter))
                    ->sort()->values()->all()
            ),
            Question::CODED_ORDERING => self::join(
                $question->options->sortBy('order')
                    ->pluck('option_letter')->map(fn ($letter) => self::letter($letter))
                    ->values()->all()
            ),
            // Cütlər SIRALI saxlanılır: 1-ci sol bənd 1-ci sağ bəndə uyğundur
            Question::CODED_MATCHING => self::join(array_map(
                fn (int $index) => ($index + 1).self::PAIR_SEPARATOR.($index + 1),
                array_keys((array) ($question->pairs ?? []))
            )),
            default => null,
        };
    }

    /**
     * Şagirdin cavabını kanonik koda çevirir: boşluqlar atılır, hərflər böyüdülür,
     * seçimdə isə sıra əhəmiyyətsiz olduğu üçün hərflər sıralanır.
     */
    public static function normalize(Question $question, ?string $given): string
    {
        $parts = array_values(array_filter(array_map(
            fn (string $part) => self::letter($part),
            explode(self::SEPARATOR, (string) $given),
        ), fn (string $part) => $part !== ''));

        if ($question->codedSubtype() === Question::CODED_MULTI_SELECT) {
            sort($parts);
        }

        return self::join($parts);
    }

    /**
     * Cavabın insan üçün oxunaqlı yazılışı (nəticə səhifəsində "düzgün cavab" sütunu).
     * Uyğunluqda cütlər mətnlə göstərilir: "1 → 2".
     */
    public static function describe(Question $question, ?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        if ($question->codedSubtype() === Question::CODED_MATCHING) {
            return implode(', ', array_map(
                fn (string $pair) => str_replace(self::PAIR_SEPARATOR, ' → ', $pair),
                explode(self::SEPARATOR, $code),
            ));
        }

        return str_replace(self::SEPARATOR, ', ', $code);
    }

    /** @param  array<int, string>  $parts */
    private static function join(array $parts): string
    {
        return implode(self::SEPARATOR, $parts);
    }

    /** Bir bəndin normal yazılışı: boşluqsuz və böyük hərflə ("a" → "A", " 1-2 " → "1-2") */
    private static function letter(string $value): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', '', trim($value)) ?? '', 'UTF-8');
    }
}
