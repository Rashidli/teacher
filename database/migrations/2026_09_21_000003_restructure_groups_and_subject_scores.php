<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qrup strukturu DİM-ə uyğunlaşır.
 *
 * - Altqruplar (I qrupda RK/Rİ, III qrupda DT/TC) ayrıca cədvəl yox, `parent_id` ilə qurulur:
 *   altqrupların balları eynidir, altqrup yalnız fənn dəstini müəyyən edir.
 * - `stage` yanlış cavabın cəriməsini müəyyən edir (config/scoring.php):
 *   II mərhələ (I–IV qrup) 0.25, I mərhələ və buraxılış 0.
 * - V qrup qabiliyyət qrupudur: testi yoxdur (`is_testable = false`).
 * - `subject_group_scores.score` → `max_score`: mənası "bir sualın balı"ndan
 *   "fənnin qrupdakı maksimal balı"na (100/150) dəyişir, ona görə decimal(4,2) sığmır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            // Altqrup: I-RK, I-RI, III-DT, III-TC
            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('groups')->cascadeOnDelete();

            $table->string('code', 20)->nullable()->after('parent_id');

            // second_stage | first_stage | final | aptitude
            $table->string('stage', 20)->default('second_stage')->after('roman_numeral');

            // V qrup (qabiliyyət) və altqrupsuz məlumat sətirləri üçün
            $table->boolean('is_testable')->default(true)->after('stage');
        });

        // "I mərhələ" qrup nömrəsi olmayan sətirdir
        Schema::table('groups', function (Blueprint $table) {
            $table->tinyInteger('number')->nullable()->change();
        });

        // Kodsuz qalan köhnə sətirləri GroupSeeder silir (unikal indeks NULL-lara mane olmur)
        Schema::table('groups', function (Blueprint $table) {
            $table->unique('code');
        });

        Schema::table('subject_group_scores', function (Blueprint $table) {
            $table->renameColumn('score', 'max_score');
        });

        Schema::table('subject_group_scores', function (Blueprint $table) {
            $table->decimal('max_score', 6, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('subject_group_scores', function (Blueprint $table) {
            $table->renameColumn('max_score', 'score');
        });

        Schema::table('subject_group_scores', function (Blueprint $table) {
            // decimal(4,2) 99.99-dan böyük balları saxlaya bilmir
            $table->decimal('score', 4, 2)->change();
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['code', 'stage', 'is_testable']);
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->tinyInteger('number')->nullable(false)->change();
        });
    }
};
