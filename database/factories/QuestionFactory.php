<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sual bankındakı sual. İmtahanla əlaqə pivotdadır:
 *   $exam->questions()->attach($question->id, ['order' => 1]);
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Question>
 */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'question_text' => 'Sual '.fake()->unique()->numberBetween(1, 99999),
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'difficulty' => Question::DIFFICULTY_MEDIUM,
            'is_active' => true,
        ];
    }

    /** Variantlı sual: verilən sayda variant, biri düzgün. */
    public function withOptions(int $count = 4, string $correct = 'A'): static
    {
        return $this->afterCreating(function (Question $question) use ($count, $correct) {
            foreach (array_slice(['A', 'B', 'C', 'D', 'E'], 0, $count) as $index => $letter) {
                $question->options()->create([
                    'option_letter' => $letter,
                    'option_text' => "Variant {$letter}",
                    'is_correct' => $letter === $correct,
                    'order' => $index + 1,
                ]);
            }
        });
    }

    /** @param  array<int, string>  $accepted */
    public function openCoded(array $accepted = ['0,5']): static
    {
        return $this->state(fn () => [
            'type' => Question::TYPE_OPEN_CODED,
            'accepted_answers' => $accepted,
        ]);
    }

    public function openWritten(): static
    {
        return $this->state(fn () => ['type' => Question::TYPE_OPEN_WRITTEN]);
    }
}
