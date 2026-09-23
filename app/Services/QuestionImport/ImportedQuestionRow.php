<?php

namespace App\Services\QuestionImport;

/**
 * Faylın bir sətri: həm önizləmədə göstərilir, həm də təsdiqdən sonra bazaya yazılır.
 * Sətirdə xəta varsa `errors` doludur və import başlamır.
 */
class ImportedQuestionRow
{
    /**
     * @param  array<int, array{option_letter: string, option_text: string, is_correct: bool}>  $options
     * @param  array<int, string>  $acceptedAnswers
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public readonly int $number,
        public readonly string $questionText,
        public readonly ?string $type,
        public readonly array $options,
        public readonly array $acceptedAnswers,
        public readonly ?string $explanation,
        public readonly ?string $gradingRubric = null,
        public readonly array $errors,
        public readonly ?int $topicId = null,
        public readonly string $difficulty = 'medium',
        public readonly ?string $topicName = null,
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** Önizləmə cədvəli üçün (Inertia props) */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'question_text' => $this->questionText,
            'type' => $this->type,
            'options' => $this->options,
            'accepted_answers' => $this->acceptedAnswers,
            'explanation' => $this->explanation,
            'grading_rubric' => $this->gradingRubric,
            'topic' => $this->topicName,
            'difficulty' => $this->difficulty,
            'errors' => $this->errors,
        ];
    }
}
