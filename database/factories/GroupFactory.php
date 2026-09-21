<?php

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
    private const NUMERALS = ['I', 'II', 'III', 'IV', 'V'];

    public function definition(): array
    {
        $number = fake()->numberBetween(1, 5);
        $numeral = self::NUMERALS[$number - 1];

        return [
            'code' => $numeral.'-'.Str::upper(Str::random(4)),
            'name' => $numeral.' qrup',
            'roman_numeral' => $numeral,
            'number' => $number,
            'stage' => Group::STAGE_SECOND,
            'is_testable' => true,
            'is_active' => true,
        ];
    }

    /** I mərhələ: yanlış cavaba cərimə yoxdur */
    public function firstStage(): static
    {
        return $this->state(fn () => [
            'stage' => Group::STAGE_FIRST,
            'name' => 'I mərhələ',
            'number' => null,
        ]);
    }

    public function subgroupOf(Group $parent): static
    {
        return $this->state(fn () => [
            'parent_id' => $parent->id,
            'roman_numeral' => $parent->roman_numeral,
            'number' => $parent->number,
            'stage' => $parent->stage,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
