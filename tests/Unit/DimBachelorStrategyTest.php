<?php

namespace Tests\Unit;

use App\Services\Scoring\DimBachelorStrategy;
use App\Services\Scoring\ScoringInput;
use Tests\TestCase;

/**
 * DİM bakalavr düsturu:
 *   NBq = max(0, Dq − Yq × penalty)
 *   NBa = Dkod + 2 × Σ(yazılı şkala qiymətləri)
 *   NB  = (NBq + NBa) × 100 / (Nq + Nkod + 2 × Nyazılı)   → 0.1-ə yuvarlaqlaşdırılır
 */
class DimBachelorStrategyTest extends TestCase
{
    private function strategy(): DimBachelorStrategy
    {
        return new DimBachelorStrategy;
    }

    /**
     * Tam formatlı imtahanda bütün cavablar düzgündürsə NB məhz 100 olmalıdır.
     * Məxrəclər DİM-in rəsmi cəmləri ilə üst-üstə düşür:
     *   II mərhələ 22+5+2×3 = 33 | xarici dil 23+2×7 = 37
     *   ana dili 20+2×10 = 40    | riyaziyyat 13+5+2×7 = 32
     *
     * @dataProvider fullFormatExams
     */
    public function test_a_perfect_paper_scores_exactly_100(int $closed, int $coded, int $written, float $expectedDenominator): void
    {
        $result = $this->strategy()->score(new ScoringInput(
            closedTotal: $closed,
            closedCorrect: $closed,
            closedWrong: 0,
            codedTotal: $coded,
            codedCorrect: $coded,
            writtenTotal: $written,
            writtenRatios: array_fill(0, $written, 1.0),
            maxScore: 100.0,
        ));

        $this->assertSame($expectedDenominator, $result->rawMax);
        $this->assertSame(100.0, $result->relativeScore);
        $this->assertSame(100.0, $result->subjectScore);
    }

    public static function fullFormatExams(): array
    {
        return [
            'II mərhələ (22 + 5 + 2×3)' => [22, 5, 3, 33.0],
            'xarici dil (23 + 2×7)' => [23, 0, 7, 37.0],
            'ana dili (20 + 2×10)' => [20, 0, 10, 40.0],
            'riyaziyyat (13 + 5 + 2×7)' => [13, 5, 7, 32.0],
        ];
    }

    public function test_wrong_answers_are_penalised_in_the_second_stage(): void
    {
        // 6 düzgün, 4 səhv: NBq = 6 − 4×0.25 = 5 → 5/10 = %50
        $result = $this->strategy()->score(new ScoringInput(
            closedTotal: 10, closedCorrect: 6, closedWrong: 4,
            codedTotal: 0, codedCorrect: 0, writtenTotal: 0,
            stage: 'second_stage',
        ));

        $this->assertSame(50.0, $result->relativeScore);
    }

    public function test_the_first_stage_has_no_penalty(): void
    {
        $result = $this->strategy()->score(new ScoringInput(
            closedTotal: 10, closedCorrect: 6, closedWrong: 4,
            codedTotal: 0, codedCorrect: 0, writtenTotal: 0,
            stage: 'first_stage',
        ));

        $this->assertSame(60.0, $result->relativeScore);
    }

    /** Cərimə balı mənfi edə bilməz. */
    public function test_the_closed_part_never_goes_below_zero(): void
    {
        $result = $this->strategy()->score(new ScoringInput(
            closedTotal: 10, closedCorrect: 1, closedWrong: 8,
            codedTotal: 0, codedCorrect: 0, writtenTotal: 0,
        ));

        $this->assertSame(0.0, $result->relativeScore);
    }

    public function test_written_answers_count_double(): void
    {
        // NBa = 2 × (1/3 + 1) = 2.6667, məxrəc = 2×2 = 4 → 66.7
        $result = $this->strategy()->score(new ScoringInput(
            closedTotal: 0, closedCorrect: 0, closedWrong: 0,
            codedTotal: 0, codedCorrect: 0,
            writtenTotal: 2, writtenRatios: [1 / 3, 1.0],
        ));

        $this->assertSame(4.0, $result->rawMax);
        $this->assertSame(66.7, $result->relativeScore);
    }

    /** Tam formatlı riyaziyyat imtahanı, qarışıq nəticə ilə. */
    public function test_a_mixed_mathematics_paper(): void
    {
        // NBq = 10 − 3×0.25 = 9.25 | NBa = 4 + 2×4.5 = 13 | (9.25+13)×100/32 = 69.53 → 69.5
        $result = $this->strategy()->score(new ScoringInput(
            closedTotal: 13, closedCorrect: 10, closedWrong: 3,
            codedTotal: 5, codedCorrect: 4,
            writtenTotal: 7, writtenRatios: [1.0, 1.0, 2 / 3, 1 / 2, 1 / 3, 0.0, 1.0],
            maxScore: 150.0,
        ));

        $this->assertSame(69.5, $result->relativeScore);
        // Fənn balı = NB × max_score / 100
        $this->assertSame(104.25, $result->subjectScore);
    }

    public function test_a_mixed_language_paper(): void
    {
        // NBq = 18 − 2×0.25 = 17.5 | NBa = 2×9 = 18 | 35.5×100/40 = 88.75 → 88.8
        $result = $this->strategy()->score(new ScoringInput(
            closedTotal: 20, closedCorrect: 18, closedWrong: 2,
            codedTotal: 0, codedCorrect: 0,
            writtenTotal: 10,
            writtenRatios: [1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 0.5, 0.5],
            maxScore: 150.0,
        ));

        $this->assertSame(88.8, $result->relativeScore);
        $this->assertSame(133.2, $result->subjectScore);
    }

    /** Qısa sınaq imtahanında da eyni düstur proporsional işləyir. */
    public function test_a_short_practice_paper_uses_the_same_formula(): void
    {
        $result = $this->strategy()->score(new ScoringInput(
            closedTotal: 4, closedCorrect: 3, closedWrong: 1,
            codedTotal: 1, codedCorrect: 1, writtenTotal: 0,
            maxScore: 150.0,
        ));

        // NBq = 3 − 0.25 = 2.75 | NBa = 1 | 3.75×100/5 = 75
        $this->assertSame(75.0, $result->relativeScore);
        $this->assertSame(112.5, $result->subjectScore);
    }

    public function test_an_empty_paper_scores_zero(): void
    {
        $result = $this->strategy()->score(new ScoringInput(
            closedTotal: 0, closedCorrect: 0, closedWrong: 0,
            codedTotal: 0, codedCorrect: 0, writtenTotal: 0,
        ));

        $this->assertSame(0.0, $result->relativeScore);
        $this->assertSame(0.0, $result->subjectScore);
    }

    /** Fənnin maksimal balı çəkili balı müəyyən edir. */
    public function test_the_subject_score_scales_with_the_subject_maximum(): void
    {
        $input = fn (float $max) => new ScoringInput(
            closedTotal: 10, closedCorrect: 5, closedWrong: 0,
            codedTotal: 0, codedCorrect: 0, writtenTotal: 0,
            maxScore: $max,
        );

        $this->assertSame(50.0, $this->strategy()->score($input(100.0))->subjectScore);
        $this->assertSame(75.0, $this->strategy()->score($input(150.0))->subjectScore);
    }
}
