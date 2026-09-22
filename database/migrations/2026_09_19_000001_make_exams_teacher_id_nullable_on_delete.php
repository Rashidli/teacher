<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * exams.teacher_id: cascadeOnDelete → nullOnDelete.
 *
 * Müəllim hesabı silinəndə onun imtahanları (və zəncirvari olaraq suallar, şagird cəhdləri,
 * cavablar) artıq silinmir; imtahan sahibsiz qalır (teacher_id = NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedBigInteger('teacher_id')->nullable()->change();

            $table->foreign('teacher_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // NOT NULL-a qaytarmazdan əvvəl sahibsiz imtahanlara sahib lazımdır: imtahanı
        // yaradan istifadəçi (created_by) götürülür, o da yoxdursa geri qaytarmaq olmur.
        if (Schema::hasColumn('exams', 'created_by')) {
            DB::table('exams')->whereNull('teacher_id')->whereNotNull('created_by')
                ->update(['teacher_id' => DB::raw('created_by')]);
        }

        $orphans = DB::table('exams')->whereNull('teacher_id')->count();

        if ($orphans > 0) {
            throw new RuntimeException(
                "exams cədvəlində teacher_id = NULL olan {$orphans} imtahan var. "
                .'Geri qaytarmadan əvvəl onlara sahib təyin edin.'
            );
        }

        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedBigInteger('teacher_id')->nullable(false)->change();

            $table->foreign('teacher_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
