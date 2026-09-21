<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Açıq (əl ilə yoxlanan) sualların qiymətləndirilməsi və DİM balı.
 *
 * - `exam_attempts.status` enum-dan string-ə keçir: yeni "pending_review" vəziyyəti lazımdır
 *   (açıq suallar yoxlanana qədər cəhd tamamlanmış sayılmır).
 * - `relative_score` — DİM-in 100 ballıq nisbi balı (NB). `total_score` isə fənnin çəkili balıdır
 *   (məs. 150-dən), yəni NB × max_score / 100.
 * - `attempt_answers.grade_ratio` — yazılı cavabın şkala qiyməti (0, 1/3, 1/2, 2/3, 1).
 *   Kəsrlər dəqiq saxlansın deyə decimal(5,4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->string('status', 20)->default('in_progress')->change();
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->decimal('relative_score', 5, 2)->default(0)->after('total_score');
            $table->timestamp('graded_at')->nullable()->after('finished_at');
        });

        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->decimal('grade_ratio', 5, 4)->nullable()->after('is_correct');
            $table->foreignId('graded_by')->nullable()->after('grade_ratio')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable()->after('graded_by');
        });

        Schema::table('attempt_answers', function (Blueprint $table) {
            // Fənnin maksimal balı 150-yə qədərdir: decimal(4,2) az sualda çatmaya bilər
            $table->decimal('score_earned', 6, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->decimal('score_earned', 4, 2)->default(0)->change();
        });

        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('graded_by');
            $table->dropColumn(['grade_ratio', 'graded_at']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn(['relative_score', 'graded_at']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->enum('status', ['in_progress', 'completed', 'timed_out'])
                ->default('in_progress')
                ->change();
        });
    }
};
