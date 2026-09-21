<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subject>
 */
class SubjectFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::ucfirst($name),
            'slug' => Str::slug($name),
            'category' => fake()->randomElement(['humanitarian', 'technical']),
            'is_active' => true,
            'order' => 0,
        ];
    }

    public function humanitarian(): static
    {
        return $this->state(fn () => ['category' => 'humanitarian']);
    }

    public function technical(): static
    {
        return $this->state(fn () => ['category' => 'technical']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
