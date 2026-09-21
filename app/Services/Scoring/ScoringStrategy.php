<?php

namespace App\Services\Scoring;

/**
 * Bal hesablama üsulu. Hazırda yalnız bakalavr (DİM) düsturu var; magistratura, MİQ,
 * sürücülük və buraxılış üçün ayrıca strategiyalar əlavə oluna bilər.
 */
interface ScoringStrategy
{
    public function name(): string;

    public function score(ScoringInput $input): ScoringResult;
}
