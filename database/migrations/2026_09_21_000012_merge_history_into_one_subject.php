<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DİM-də "Tarix" BİR fəndir: imtahanda bir bölmə və bir maksimal bal olur.
 *
 * Bazada isə iki ayrı fənn vardı — "Azərbaycan tarixi" və "Ümumi tarix". Onlar indi "Tarix"
 * fənninin altında MÖVZU QRUPLARI olur (`topics.parent_id` ilə real mövzular onların altına düşür).
 *
 * Köçürülən istinadlar: suallar, imtahanlar, bölmələr, cəhd nəticələri, bal matrisi, müəllim
 * fənləri və kateqoriya pivotu. Geri qaytarma mümkündür: hər sual hansı mövzu qrupundadırsa,
 * köhnə fənninə qaytarılır.
 */
return new class extends Migration
{
    private const MERGED = ['azerbaycan-tarixi' => 'Azərbaycan tarixi', 'umumi-tarix' => 'Ümumi tarix'];

    public function up(): void
    {
        $old = DB::table('subjects')->whereIn('slug', array_keys(self::MERGED))->pluck('id', 'slug');

        if ($old->isEmpty()) {
            return;
        }

        $historyId = $this->historySubjectId();

        foreach (self::MERGED as $slug => $name) {
            $oldId = $old->get($slug);

            if (! $oldId) {
                continue;
            }

            $groupTopicId = $this->topicGroup($historyId, $name);

            // Köhnə fənnin mövzuları qrupun altına düşür
            DB::table('topics')->where('subject_id', $oldId)->update([
                'subject_id' => $historyId,
                'parent_id' => $groupTopicId,
            ]);

            // Sualları köçürməzdən ƏVVƏL siyahını götürürük: mövzusuz olanlar qrupa bağlanır
            $questionIds = DB::table('questions')->where('subject_id', $oldId)->pluck('id');

            DB::table('questions')->whereIn('id', $questionIds)->update(['subject_id' => $historyId]);
            DB::table('questions')->whereIn('id', $questionIds)->whereNull('topic_id')
                ->update(['topic_id' => $groupTopicId]);

            DB::table('exams')->where('subject_id', $oldId)->update(['subject_id' => $historyId]);
            DB::table('attempt_sections')->where('subject_id', $oldId)->update(['subject_id' => $historyId]);
            $this->movePivot('exam_sections', 'exam_id', $oldId, $historyId);
            $this->movePivot('subject_group_scores', 'group_id', $oldId, $historyId);
            $this->movePivot('category_subject', 'category_id', $oldId, $historyId);
            $this->movePivot('subject_teacher', 'user_id', $oldId, $historyId);

            DB::table('subjects')->where('id', $oldId)->delete();
        }
    }

    private function historySubjectId(): int
    {
        $existing = DB::table('subjects')->where('slug', 'tarix')->value('id');

        if ($existing) {
            return $existing;
        }

        return DB::table('subjects')->insertGetId([
            'name' => 'Tarix',
            'slug' => 'tarix',
            'category' => 'humanitarian',
            'is_active' => true,
            'order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function topicGroup(int $subjectId, string $name): int
    {
        $slug = str($name)->slug()->value();

        $existing = DB::table('topics')->where('subject_id', $subjectId)->where('slug', $slug)->value('id');

        if ($existing) {
            return $existing;
        }

        return DB::table('topics')->insertGetId([
            'subject_id' => $subjectId,
            'name' => $name,
            'slug' => $slug,
            'order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Pivot sətirlərini yeni fənnə köçürür; hədəf artıq varsa köhnə sətir silinir
     * (unikal indeks pozulmasın).
     */
    private function movePivot(string $table, string $otherKey, int $oldId, int $newId): void
    {
        foreach (DB::table($table)->where('subject_id', $oldId)->get() as $row) {
            $exists = DB::table($table)
                ->where('subject_id', $newId)
                ->where($otherKey, $row->{$otherKey})
                ->exists();

            $exists
                ? DB::table($table)->where('id', $row->id)->delete()
                : DB::table($table)->where('id', $row->id)->update(['subject_id' => $newId]);
        }
    }

    public function down(): void
    {
        $historyId = DB::table('subjects')->where('slug', 'tarix')->value('id');

        if (! $historyId) {
            return;
        }

        foreach (self::MERGED as $slug => $name) {
            $groupTopicId = DB::table('topics')->where('subject_id', $historyId)
                ->where('slug', str($name)->slug()->value())->value('id');

            if (! $groupTopicId) {
                continue;
            }

            $oldId = DB::table('subjects')->insertGetId([
                'name' => $name,
                'slug' => $slug,
                'category' => 'humanitarian',
                'is_active' => true,
                'order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Mövzu qrupuna (və onun alt mövzularına) bağlı suallar köhnə fənnə qayıdır
            $topicIds = DB::table('topics')
                ->where('id', $groupTopicId)
                ->orWhere('parent_id', $groupTopicId)
                ->pluck('id');

            DB::table('questions')->whereIn('topic_id', $topicIds)->update(['subject_id' => $oldId]);
            DB::table('topics')->where('parent_id', $groupTopicId)->update([
                'subject_id' => $oldId,
                'parent_id' => null,
            ]);
            DB::table('topics')->where('id', $groupTopicId)->delete();
        }
    }
};
