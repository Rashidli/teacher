<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Subject;
use App\Models\SubjectGroupScore;
use Illuminate\Database\Seeder;

/**
 * Fənnin qrupdakı MAKSİMAL balı (bir sualın balı deyil).
 *
 * Qeydlər:
 * - DİM-in "Tarix" fənni bu bazada iki fənnə bölünüb (Azərbaycan tarixi və Ümumi tarix),
 *   ona görə hər ikisinə eyni bal verilir.
 * - "Ana dili" → Azərbaycan dili. Rus sektoru əlavə olunanda (P2) Rus dili də ana dili kimi
 *   hesablanacaq.
 * - "Xarici dil" → mövcud bütün dillər (İngilis, Rus, Fransız, Alman).
 * - Ballar baş qrupa bağlanır: altqrupların (RK/Rİ, DT/TC) balları eynidir.
 */
class SubjectGroupScoreSeeder extends Seeder
{
    private const TARIX = ['azerbaycan-tarixi', 'umumi-tarix'];

    private const XARICI_DIL = ['ingilis-dili', 'rus-dili', 'fransiz-dili', 'alman-dili'];

    public function run(): void
    {
        $matrix = [
            'I' => [
                'riyaziyyat' => 150,
                'fizika' => 150,
                'kimya' => 100,
                'informatika' => 100,
            ],
            'II' => [
                'riyaziyyat' => 150,
                ...array_fill_keys(self::TARIX, 100),
                'cografiya' => 150,
            ],
            'III' => [
                'azerbaycan-dili' => 150,
                'edebiyyat' => 100,
                'cografiya' => 100,
                ...array_fill_keys(self::TARIX, 150),
            ],
            'IV' => [
                'fizika' => 100,
                'kimya' => 150,
                'biologiya' => 150,
            ],
            'I-MERHELE' => [
                'azerbaycan-dili' => 100,
                'riyaziyyat' => 100,
                ...array_fill_keys(self::XARICI_DIL, 100),
            ],
        ];

        $subjects = Subject::pluck('id', 'slug');
        $groups = Group::whereIn('code', array_keys($matrix))->pluck('id', 'code');

        foreach ($matrix as $groupCode => $scores) {
            $groupId = $groups->get($groupCode);

            if (! $groupId) {
                continue;
            }

            $keep = [];

            foreach ($scores as $slug => $maxScore) {
                $subjectId = $subjects->get($slug);

                if (! $subjectId) {
                    continue;
                }

                SubjectGroupScore::updateOrCreate(
                    ['subject_id' => $subjectId, 'group_id' => $groupId],
                    ['max_score' => $maxScore],
                );

                $keep[] = $subjectId;
            }

            // Köhnə matrisdən qalan, bu qrupa aid olmayan fənlər silinir
            SubjectGroupScore::where('group_id', $groupId)
                ->whereNotIn('subject_id', $keep)
                ->delete();
        }

        // Matrisdə ümumiyyətlə olmayan qruplar (V, altqruplar) bal saxlamır
        SubjectGroupScore::whereNotIn('group_id', $groups->values())->delete();
    }
}
