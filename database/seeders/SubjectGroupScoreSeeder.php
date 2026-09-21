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
 * - "Tarix" bir fənndir; "Azərbaycan tarixi" və "Ümumi tarix" onun mövzu qruplarıdır.
 * - "Ana dili" sektora görə dəyişir: az sektorunda Azərbaycan dili, ru sektorunda Rus dili.
 *   Balı eynidir (III qrupda 150, I mərhələdə 100) — imtahanın sektoru hansı fənnin
 *   işlədiləcəyini müəyyən edir (`category_subject.sector`).
 * - "Xarici dil" → mövcud bütün dillər (İngilis, Rus, Fransız, Alman).
 * - Ballar baş qrupa bağlanır: altqrupların (RK/Rİ, DT/TC) balları eynidir.
 */
class SubjectGroupScoreSeeder extends Seeder
{
    /** DİM-də "Tarix" bir fəndir (bax: 2026_09_21_000012 migration) */
    private const TARIX = ['tarix'];

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
                // Ana dili: az sektorunda Azərbaycan dili, ru sektorunda Rus dili
                'azerbaycan-dili' => 150,
                'rus-dili' => 150,
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
