<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['name' => 'I Qrup', 'roman_numeral' => 'I', 'number' => 1, 'description' => 'Texniki-riyaziyyat'],
            ['name' => 'II Qrup', 'roman_numeral' => 'II', 'number' => 2, 'description' => 'Tibb-biologiya'],
            ['name' => 'III Qrup', 'roman_numeral' => 'III', 'number' => 3, 'description' => 'Humanitar'],
            ['name' => 'IV Qrup', 'roman_numeral' => 'IV', 'number' => 4, 'description' => 'Dillər'],
        ];

        foreach ($groups as $group) {
            Group::create($group);
        }
    }
}
