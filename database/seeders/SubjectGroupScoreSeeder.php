<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\Group;
use App\Models\SubjectGroupScore;
use Illuminate\Database\Seeder;

class SubjectGroupScoreSeeder extends Seeder
{
    public function run(): void
    {
        // Bal matrisi: [subject_slug => [group_number => score]]
        $scoreMatrix = [
            'riyaziyyat' => [1 => 4, 2 => 4, 3 => 2, 4 => 2],
            'fizika' => [1 => 4, 2 => 2, 3 => 2, 4 => 2],
            'kimya' => [1 => 2, 2 => 4, 3 => 2, 4 => 2],
            'biologiya' => [1 => 2, 2 => 4, 3 => 2, 4 => 2],
            'azerbaycan-dili' => [1 => 1, 2 => 1, 3 => 1, 4 => 1],
            'azerbaycan-tarixi' => [1 => 2, 2 => 2, 3 => 4, 4 => 4],
            'umumi-tarix' => [1 => 2, 2 => 2, 3 => 4, 4 => 4],
            'edebiyyat' => [1 => 2, 2 => 2, 3 => 4, 4 => 4],
            'ingilis-dili' => [1 => 4, 2 => 4, 3 => 8, 4 => 8],
            'rus-dili' => [1 => 4, 2 => 4, 3 => 8, 4 => 8],
            'fransiz-dili' => [1 => 4, 2 => 4, 3 => 8, 4 => 8],
            'alman-dili' => [1 => 4, 2 => 4, 3 => 8, 4 => 8],
            'cografiya' => [1 => 2, 2 => 2, 3 => 2, 4 => 4],
            'informatika' => [1 => 4, 2 => 2, 3 => 2, 4 => 2],
        ];

        $subjects = Subject::all()->keyBy('slug');
        $groups = Group::all()->keyBy('number');

        foreach ($scoreMatrix as $slug => $groupScores) {
            $subject = $subjects->get($slug);
            if (!$subject) continue;

            foreach ($groupScores as $groupNumber => $score) {
                $group = $groups->get($groupNumber);
                if (!$group) continue;

                SubjectGroupScore::create([
                    'subject_id' => $subject->id,
                    'group_id' => $group->id,
                    'score' => $score,
                ]);
            }
        }
    }
}
