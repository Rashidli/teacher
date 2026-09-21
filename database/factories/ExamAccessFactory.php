<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExamAccess>
 */
class ExamAccessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'exam_id' => Exam::factory(),
            'source' => ExamAccess::SOURCE_MANUAL,
            'expires_at' => null,
            'attempts_allowed' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
