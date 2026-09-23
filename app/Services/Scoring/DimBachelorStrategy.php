<?php

namespace App\Services\Scoring;

/**
 * DİM bakalavr düsturu:
 *
 *   NBq = max(0, Dq − Yq × penalty)
 *   NBa = Dkod + w × Σ(yazılı cavabların şkala qiymətləri)
 *   NB  = (NBq + NBa) × 100 / (Nq + Nkod + w × Nyazılı)      → 0.1-ə yuvarlaqlaşdırılır
 *   Fənn balı = NB × max_score / 100
 *
 * Tam formatlı imtahanda məxrəc DİM-in rəsmi cəmi ilə üst-üstə düşür, məsələn:
 *   II mərhələ 22 + 5 + 2×3 = 33 | xarici dil 23 + 2×7 = 37
 *   ana dili 20 + 2×10 = 40      | riyaziyyat 13 + 5 + 2×7 = 32
 * Qısa (sınaq) imtahanlarda eyni düstur proporsional işləyir.
 */
class DimBachelorStrategy implements ScoringStrategy
{
    public function name(): string
    {
        return 'dim_bachelor';
    }

    public function score(ScoringInput $input): ScoringResult
    {
        $weight = (float) config('scoring.open_written_weight', 2);
        $penalty = $this->penaltyFor($input->stage);

        // Qapalı suallar: yanlış cavab cərimə gətirir, nəticə mənfi ola bilməz
        $closedPoints = max(0.0, $input->closedCorrect - $input->closedWrong * $penalty);

        // Açıq suallar: kodlaşdırılan 1, yazılı isə şkala qiyməti × çəki
        $openPoints = $input->codedCorrect + $weight * array_sum($input->writtenRatios);

        $rawScore = $closedPoints + $openPoints;
        $rawMax = $input->closedTotal + $input->codedTotal + $weight * $input->writtenTotal;

        if ($rawMax <= 0) {
            return new ScoringResult(0.0, 0.0, 0.0, 0.0, $input->maxScore);
        }

        $relative = $this->round($rawScore * 100 / $rawMax);

        return new ScoringResult(
            relativeScore: $relative,
            subjectScore: round($relative * $input->maxScore / 100, 2),
            rawScore: $rawScore,
            rawMax: $rawMax,
            maxScore: $input->maxScore,
        );
    }

    /**
     * Yanlış cavabın cəriməsi imtahanın mərhələsindən gəlir.
     *
     * Mərhələ isə DİM bal qrupundandır; sürücülük, MİQ, sertifikasiya, magistratura və
     * dövlət qulluğu imtahanının belə qrupu yoxdur (`exams.group_id = NULL`) — orada
     * cərimə tətbiq EDİLMİR (`scoring.penalty_without_group`).
     *
     * KEÇİCİ HƏLL: bu kateqoriyaların öz qaydaları var (dövlət qulluğunda açıq sual 2 bal,
     * sürücülükdə keçdi/kəsildi). Onlar ROADMAP P3-dəki ayrıca `ScoringStrategy`-lərə
     * qədər hamısı bu DİM düsturu ilə hesablanır — demo və sınaq üçün kifayətdir.
     */
    private function penaltyFor(?string $stage): float
    {
        if ($stage === null) {
            return (float) config('scoring.penalty_without_group', 0.0);
        }

        $penalties = (array) config('scoring.penalty_per_wrong', []);

        return (float) ($penalties[$stage] ?? 0.0);
    }

    private function round(float $value): float
    {
        $step = (float) config('scoring.relative_score_step', 0.1);

        return $step > 0 ? round(round($value / $step) * $step, 4) : $value;
    }
}
