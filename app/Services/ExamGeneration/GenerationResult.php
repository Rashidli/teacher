<?php

namespace App\Services\ExamGeneration;

use App\Models\Exam;
use Illuminate\Support\Collection;

/**
 * Generasiyanın nəticəsi: ya yaradılan qaralamalar, ya da bankda nəyin çatmadığı.
 * Çatışmazlıq varsa HEÇ NƏ yaradılmır — yarımçıq imtahan qalmasın.
 */
class GenerationResult
{
    /**
     * @param  Collection<int, Exam>  $exams
     * @param  array<int, array{subject: string, requested: int, available: int, missing: int}>  $shortfalls
     */
    public function __construct(
        public readonly Collection $exams,
        public readonly array $shortfalls = [],
    ) {
    }

    public function failed(): bool
    {
        return $this->shortfalls !== [];
    }

    /** Admin formasında göstərilən mesajlar */
    public function shortfallMessages(): array
    {
        return array_map(
            fn (array $row) => "{$row['subject']}: {$row['requested']} sual istənilib, bankda "
                ."{$row['available']} uyğun sual var — {$row['missing']} çatmır.",
            $this->shortfalls
        );
    }
}
