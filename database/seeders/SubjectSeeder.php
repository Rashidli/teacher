<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            // Humanitarian
            ['name' => 'Azərbaycan dili', 'category' => 'humanitarian', 'order' => 1],
            ['name' => 'Azərbaycan tarixi', 'category' => 'humanitarian', 'order' => 2],
            ['name' => 'Ümumi tarix', 'category' => 'humanitarian', 'order' => 3],
            ['name' => 'Ədəbiyyat', 'category' => 'humanitarian', 'order' => 4],
            ['name' => 'İngilis dili', 'category' => 'humanitarian', 'order' => 5],
            ['name' => 'Rus dili', 'category' => 'humanitarian', 'order' => 6],
            ['name' => 'Fransız dili', 'category' => 'humanitarian', 'order' => 7],
            ['name' => 'Alman dili', 'category' => 'humanitarian', 'order' => 8],
            // Technical
            ['name' => 'Riyaziyyat', 'category' => 'technical', 'order' => 9],
            ['name' => 'Fizika', 'category' => 'technical', 'order' => 10],
            ['name' => 'Kimya', 'category' => 'technical', 'order' => 11],
            ['name' => 'Biologiya', 'category' => 'technical', 'order' => 12],
            ['name' => 'Coğrafiya', 'category' => 'technical', 'order' => 13],
            ['name' => 'İnformatika', 'category' => 'technical', 'order' => 14],
        ];

        foreach ($subjects as $subject) {
            Subject::create([
                'name' => $subject['name'],
                'slug' => Str::slug($subject['name']),
                'category' => $subject['category'],
                'order' => $subject['order'],
                'is_active' => true,
            ]);
        }
    }
}
