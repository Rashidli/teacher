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
        // NOT NULL-a qaytarmazdan əvvəl sahibsiz imtahanlara sahib lazımdır.
        $orphans = DB::table('exams')->whereNull('teacher_id')->count();

        if ($orphans > 0) {
            $ownerId = config('features.exam_owner_id');

            if (! $ownerId || ! DB::table('users')->where('id', $ownerId)->exists()) {
                throw new RuntimeException(
                    "exams cədvəlində teacher_id = NULL olan {$orphans} imtahan var. "
                    .'Geri qaytarmaq üçün .env-də mövcud istifadəçinin ID-si ilə EXAM_OWNER_ID təyin edin.'
                );
            }

            DB::table('exams')->whereNull('teacher_id')->update(['teacher_id' => $ownerId]);
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
