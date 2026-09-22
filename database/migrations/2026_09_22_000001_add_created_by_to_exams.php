<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * exams.created_by — imtahanı yaradan istifadəçi (istənilən rolda: admin və ya müəllim).
 *
 * Əvvəl bu məlumat teacher_id-də saxlanılırdı və müəllim modulu söndürülü olanda
 * .env-dəki EXAM_OWNER_ID ilə süni şəkildə doldurulurdu. İndi sahiblik created_by-dadır,
 * teacher_id isə yalnız müəllim modulunda (imtahan hansı müəllimə aiddir) işlənir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('teacher_id')
                ->constrained('users')->nullOnDelete();
        });

        // Mövcud imtahanların sahibi indiyə qədər teacher_id idi
        DB::table('exams')->whereNull('created_by')->update([
            'created_by' => DB::raw('teacher_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
