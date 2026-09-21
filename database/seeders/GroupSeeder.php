<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DİM qrupları. `code` üzrə idempotent upsert — seeder təkrar işlədilə bilər.
 *
 * Altqruplar (RK/Rİ, DT/TC) yalnız fənn dəstini göstərir: balları baş qrupla eynidir,
 * ona görə `subject_group_scores` baş qrupa bağlanır.
 *
 * Siyahıda olmayan köhnə qruplar silinir. Onlara bağlı imtahanlar əvvəlcə I qrupa keçirilir
 * (exams.group_id cascade olduğu üçün əks halda imtahanlar da silinərdi).
 */
class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'code' => 'I', 'name' => 'I qrup', 'roman_numeral' => 'I', 'number' => 1,
                'stage' => Group::STAGE_SECOND,
                'description' => 'Riyaziyyat, Fizika, Kimya / İnformatika',
                'children' => [
                    ['code' => 'I-RK', 'name' => 'I qrup — RK altqrupu', 'description' => 'Riyaziyyat, Fizika, Kimya'],
                    ['code' => 'I-RI', 'name' => 'I qrup — Rİ altqrupu', 'description' => 'Riyaziyyat, Fizika, İnformatika'],
                ],
            ],
            [
                'code' => 'II', 'name' => 'II qrup', 'roman_numeral' => 'II', 'number' => 2,
                'stage' => Group::STAGE_SECOND,
                'description' => 'Riyaziyyat, Tarix, Coğrafiya',
            ],
            [
                'code' => 'III', 'name' => 'III qrup', 'roman_numeral' => 'III', 'number' => 3,
                'stage' => Group::STAGE_SECOND,
                'description' => 'Ana dili, Ədəbiyyat / Coğrafiya, Tarix',
                'children' => [
                    ['code' => 'III-DT', 'name' => 'III qrup — DT altqrupu', 'description' => 'Ana dili, Ədəbiyyat, Tarix'],
                    ['code' => 'III-TC', 'name' => 'III qrup — TC altqrupu', 'description' => 'Ana dili, Coğrafiya, Tarix'],
                ],
            ],
            [
                'code' => 'IV', 'name' => 'IV qrup', 'roman_numeral' => 'IV', 'number' => 4,
                'stage' => Group::STAGE_SECOND,
                'description' => 'Fizika, Kimya, Biologiya',
            ],
            [
                'code' => 'V', 'name' => 'V qrup', 'roman_numeral' => 'V', 'number' => 5,
                'stage' => Group::STAGE_APTITUDE, 'is_testable' => false,
                'description' => 'Qabiliyyət qrupu — imtahan testi yoxdur',
            ],
            [
                // Nömrəsi olmayan ayrıca "qrup": yanlış cavaba cərimə tətbiq edilmir
                'code' => 'I-MERHELE', 'name' => 'I mərhələ', 'roman_numeral' => 'I', 'number' => null,
                'stage' => Group::STAGE_FIRST,
                'description' => 'Ana dili, Riyaziyyat, Xarici dil',
            ],
        ];

        $keep = [];

        foreach ($groups as $group) {
            $children = $group['children'] ?? [];
            unset($group['children']);

            $parent = $this->upsert($group);
            $keep[] = $parent->id;

            foreach ($children as $child) {
                $keep[] = $this->upsert($child + [
                    'parent_id' => $parent->id,
                    'roman_numeral' => $parent->roman_numeral,
                    'number' => $parent->number,
                    'stage' => $parent->stage,
                ])->id;
            }
        }

        $this->removeOtherGroups($keep);
    }

    private function upsert(array $attributes): Group
    {
        $code = $attributes['code'];
        unset($attributes['code']);

        return Group::updateOrCreate(
            ['code' => $code],
            $attributes + ['is_active' => true, 'is_testable' => true]
        );
    }

    /** @param  array<int, int>  $keep */
    private function removeOtherGroups(array $keep): void
    {
        $obsolete = Group::whereNotIn('id', $keep)->pluck('id');

        if ($obsolete->isEmpty()) {
            return;
        }

        $fallback = Group::where('code', 'I')->value('id');

        // Silinən qrupa bağlı imtahanlar itməsin (exams.group_id cascade-dir)
        DB::table('exams')->whereIn('group_id', $obsolete)->update(['group_id' => $fallback]);
        DB::table('exam_attempts')->whereIn('group_id', $obsolete)->update(['group_id' => $fallback]);

        Group::whereIn('id', $obsolete)->delete();
    }
}
