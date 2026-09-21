<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 5);

        return [
            'name' => ['I', 'II', 'III', 'IV', 'V'][$number - 1].' qrup',
            'roman_numeral' => ['I', 'II', 'III', 'IV', 'V'][$number - 1],
            'number' => $number,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
