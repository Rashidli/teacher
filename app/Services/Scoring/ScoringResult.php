<?php

namespace App\Services\Scoring;

class ScoringResult
{
    public function __construct(
        /** DİM-in 100 ballıq nisbi balı (NB) */
        public readonly float $relativeScore,
        /** Fənnin çəkili balı: NB × max_score / 100 */
        public readonly float $subjectScore,
        /** NBq + NBa */
        public readonly float $rawScore,
        /** Məxrəc: Nq + Nkod + w × Nyazılı */
        public readonly float $rawMax,
        /** Fənnin qrupdakı maksimal balı */
        public readonly float $maxScore,
    ) {
    }

    /**
     * Bir xam bal vahidinin fənn balı ilə qiyməti.
     * Ayrı-ayrı cavablara bal yazmaq üçün istifadə olunur (cərimə cəhd səviyyəsindədir).
     */
    public function subjectPointsPerRawPoint(): float
    {
        return $this->rawMax > 0 ? $this->maxScore / $this->rawMax : 0.0;
    }
}
