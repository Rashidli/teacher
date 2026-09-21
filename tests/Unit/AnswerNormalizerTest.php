<?php

namespace Tests\Unit;

use App\Support\AnswerNormalizer;
use PHPUnit\Framework\TestCase;

class AnswerNormalizerTest extends TestCase
{
    /**
     * Admin yalnız "0,5" yazıb: bütün ekvivalent yazılışlar qəbul olunmalıdır.
     *
     * @dataProvider equivalentHalves
     */
    public function test_it_accepts_every_numeric_spelling_of_the_same_value(string $given): void
    {
        $this->assertTrue(AnswerNormalizer::matches($given, ['0,5']));
    }

    public static function equivalentHalves(): array
    {
        return [
            'vergüllü' => ['0,5'],
            'nöqtəli' => ['0.5'],
            'sıfırsız' => ['.5'],
            'kəsr' => ['1/2'],
            'boşluqlu' => [' 0.5 '],
            'daxili boşluq' => ['0 , 5'],
            'genişlənmiş kəsr' => ['2/4'],
        ];
    }

    public function test_it_rejects_a_different_number(): void
    {
        $this->assertFalse(AnswerNormalizer::matches('0,6', ['0,5']));
        $this->assertFalse(AnswerNormalizer::matches('5', ['0,5']));
    }

    public function test_it_handles_negative_numbers_and_unicode_minus(): void
    {
        $this->assertTrue(AnswerNormalizer::matches('-3', ['-3']));
        $this->assertTrue(AnswerNormalizer::matches("\u{2212}3", ['-3']));
        $this->assertFalse(AnswerNormalizer::matches('3', ['-3']));
    }

    public function test_it_tolerates_floating_point_error(): void
    {
        $this->assertTrue(AnswerNormalizer::matches('0.1', ['1/10']));
        $this->assertTrue(AnswerNormalizer::matches((string) (0.1 + 0.2), ['0,3']));
    }

    public function test_it_falls_back_to_text_comparison_for_non_numeric_answers(): void
    {
        $this->assertTrue(AnswerNormalizer::matches('X = 2', ['x = 2']));
        $this->assertTrue(AnswerNormalizer::matches('  x  =  2 ', ['x = 2']));
        $this->assertFalse(AnswerNormalizer::matches('x = 3', ['x = 2']));
    }

    /** Ədədi olmayan alternativlər accepted_answers-də sadalanır. */
    public function test_it_accepts_any_of_the_listed_alternatives(): void
    {
        $accepted = ['2', 'x=2', 'iki'];

        $this->assertTrue(AnswerNormalizer::matches('2', $accepted));
        $this->assertTrue(AnswerNormalizer::matches('X=2', $accepted));
        $this->assertFalse(AnswerNormalizer::matches('üç', $accepted));
    }

    /**
     * Azərbaycan əlifbasında "İ/i" və "I/ı" ayrı hərflərdir: böyük "İKİ" kiçik "iki"-yə
     * bərabərdir, nöqtəsiz "IKI" isə başqa sözdür.
     */
    public function test_it_uses_azerbaijani_letter_casing(): void
    {
        $this->assertTrue(AnswerNormalizer::matches('İKİ', ['iki']));
        $this->assertTrue(AnswerNormalizer::matches('iki', ['İki']));
        $this->assertTrue(AnswerNormalizer::matches('QIZIL', ['qızıl']));
        $this->assertFalse(AnswerNormalizer::matches('IKI', ['iki']));
    }

    public function test_an_empty_answer_never_matches(): void
    {
        $this->assertFalse(AnswerNormalizer::matches(null, ['0,5']));
        $this->assertFalse(AnswerNormalizer::matches('', ['0,5']));
        $this->assertFalse(AnswerNormalizer::matches('   ', ['0,5']));
        // Boş qəbul siyahısı da uyğunluq vermir
        $this->assertFalse(AnswerNormalizer::matches('0,5', []));
        $this->assertFalse(AnswerNormalizer::matches('0,5', ['', null]));
    }

    public function test_division_by_zero_is_not_a_number(): void
    {
        $this->assertNull(AnswerNormalizer::toNumber('1/0'));
        $this->assertFalse(AnswerNormalizer::matches('1/0', ['0']));
    }

    public function test_to_number_returns_null_for_expressions_it_can_not_read(): void
    {
        $this->assertNull(AnswerNormalizer::toNumber('2·10^3'));
        $this->assertNull(AnswerNormalizer::toNumber('x=2'));
        $this->assertSame(0.5, AnswerNormalizer::toNumber('1/2'));
    }
}
