<?php

namespace App\Services\Scoring;

/**
 * Bal hesablaması üçün xam göstəricilər. Modelə bağlı deyil — birbaşa unit testdən çağırıla bilər.
 */
class ScoringInput
{
    /**
     * @param  array<int, float>  $writtenRatios  qiymətləndirilmiş yazılı cavabların şkala qiymətləri
     */
    public function __construct(
        public readonly int $closedTotal,
        public readonly int $closedCorrect,
        public readonly int $closedWrong,
        public readonly int $codedTotal,
        public readonly int $codedCorrect,
        public readonly int $writtenTotal,
        public readonly array $writtenRatios = [],
        public readonly float $maxScore = 100.0,
        public readonly string $stage = 'second_stage',
    ) {
    }
}
