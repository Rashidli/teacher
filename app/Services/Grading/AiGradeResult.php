<?php

namespace App\Services\Grading;

/**
 * AI qiymətləndirməsinin nəticəsi.
 *
 * Uğursuz halda `ratio` null olur və `reason` səbəbi saxlayır — cavab `pending_review`
 * qalır, admin əl ilə qiymətləndirir. Token sayları uğursuz cavabda da doldurulur
 * (sorğu getdiyi üçün pul yanıb), beləliklə xərc hesabatı düz qalır.
 */
class AiGradeResult
{
    public function __construct(
        public readonly ?float $ratio,
        public readonly ?string $comment,
        public readonly ?string $model = null,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly ?string $reason = null,
    ) {}

    public function successful(): bool
    {
        return $this->ratio !== null;
    }

    public static function failed(string $reason, ?string $model = null, int $inputTokens = 0, int $outputTokens = 0): self
    {
        return new self(null, null, $model, $inputTokens, $outputTokens, $reason);
    }
}
