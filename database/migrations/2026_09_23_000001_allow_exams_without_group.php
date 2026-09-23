<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `exams.group_id` və `exam_attempts.group_id`: NOT NULL → nullable, cascade → nullOnDelete.
 *
 * DİM bal qrupları (I–V, I mərhələ) YALNIZ abituriyent qəbuluna aiddir. Sürücülük, MİQ,
 * sertifikasiya, magistratura və dövlət qulluğu imtahanının belə qrupu yoxdur — əvvəllər
 * onlara uydurma qrup (I qrup) yazılırdı və bu, statistikada və hesabatlarda səhv
 * qruplaşdırma yaradırdı. İndi belə imtahanlarda sahə NULL qalır.
 *
 * Bal hesablaması buna hazırdır: `AttemptScorer::maxScore()` qrup olmayanda
 * `scoring.default_max_score`-a düşür, cərimə əmsalı isə `scoring.penalty_without_group`
 * ilə sıfırdır (bax `DimBachelorStrategy`).
 *
 * `nullOnDelete`: qrup silinəndə imtahan (və zəncirvari olaraq sualları, cəhdləri) artıq
 * silinmir, sadəcə qrupsuz qalır. `GroupSeeder` imtahanları əvvəlcədən köçürməyə davam edir —
 * o, qrupu MƏNALI olan imtahanları doğru qrupda saxlayır, bu FK isə yalnız son qoruyucudur.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['exams', 'exam_attempts'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['group_id']);
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('group_id')->nullable()->change();

                $blueprint->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // NOT NULL-a qayıtmaq üçün qrupsuz sətirlərə qrup lazımdır — uydurmaq olmaz,
        // ona görə geri qaytarma yalnız belə sətir olmayanda mümkündür.
        foreach (['exams', 'exam_attempts'] as $table) {
            $orphans = DB::table($table)->whereNull('group_id')->count();

            if ($orphans > 0) {
                throw new RuntimeException(
                    "{$table} cədvəlində group_id = NULL olan {$orphans} sətir var. "
                    .'Geri qaytarmadan əvvəl onlara qrup təyin edin.'
                );
            }
        }

        foreach (['exams', 'exam_attempts'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['group_id']);
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('group_id')->nullable(false)->change();

                $blueprint->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            });
        }
    }
};
