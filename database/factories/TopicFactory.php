<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Topic>
 */
class TopicFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::ucfirst(fake()->unique()->words(2, true));

        return [
            'subject_id' => Subject::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'quarter' => null,
            'order' => 0,
            'is_active' => true,
        ];
    }

    public function quarter(int $quarter): static
    {
        return $this->state(fn () => ['quarter' => $quarter]);
    }
}
