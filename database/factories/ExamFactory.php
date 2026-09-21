<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Exam>
 */
class ExamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'teacher_id' => User::factory()->admin(),
            'subject_id' => Subject::factory(),
            'group_id' => Group::factory(),
            'title' => 'Sınaq imtahanı '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->sentence(),
            'duration_minutes' => 60,
            'price' => 0,
            'is_free' => true,
            'is_active' => false,
            'is_published' => false,
            'created_by_admin' => true,
        ];
    }

    /** Hər imtahanın ən azı bir bölməsi olur (migration və controller bunu təmin edir). */
    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Exam $exam) {
            if ($exam->sections()->doesntExist()) {
                $exam->sections()->create([
                    'subject_id' => $exam->subject_id,
                    'order' => 1,
                ]);
            }
        });
    }

    public function paid(float $price = 10.00): static
    {
        return $this->state(fn () => ['is_free' => false, 'price' => $price]);
    }

    /** Şagirdin görə biləcəyi imtahan: həm aktiv, həm yayımlanmış. */
    public function published(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
