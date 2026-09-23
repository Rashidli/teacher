<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Açıq yazılı cavabların avtomatik (AI) qiymətləndirilməsi üçün sahələr.
 *
 * - `questions.grading_rubric` — düzgün cavab və qiymətləndirmə meyarları. Modelə məhz bu
 *   mətn göndərilir; boşdursa sual AI-yə getmir (meyar olmadan qiymət uydurulmasın).
 * - `attempt_answers.grade_source` — qiyməti kim verib: `ai` və ya `admin`. Adminin qiyməti
 *   AI-nin qiymətini həmişə üstələyir.
 * - `grade_comment` — 1–2 cümləlik əsaslandırma (şagird və admin görür).
 * - `ai_*` sütunları — xərc nəzarəti: hansı model, neçə token.
 * - `review_requested_at` — şagird avtomatik qiymətə etiraz edib, admin növbəsində yuxarı çıxır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('grading_rubric')->nullable()->after('explanation');
        });

        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->string('grade_source', 10)->nullable()->after('grade_ratio');
            $table->text('grade_comment')->nullable()->after('grade_source');
            $table->string('ai_model', 60)->nullable()->after('grade_comment');
            $table->unsignedInteger('ai_input_tokens')->nullable()->after('ai_model');
            $table->unsignedInteger('ai_output_tokens')->nullable()->after('ai_input_tokens');
            $table->timestamp('ai_graded_at')->nullable()->after('ai_output_tokens');
            $table->timestamp('review_requested_at')->nullable()->after('graded_at');

            // Admin növbəsi: yoxlanmamış və etiraz edilmiş cavabları tez tapsın
            $table->index(['grade_source', 'review_requested_at'], 'attempt_answers_review_index');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('grading_rubric');
        });

        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->dropIndex('attempt_answers_review_index');
            $table->dropColumn([
                'grade_source', 'grade_comment', 'ai_model',
                'ai_input_tokens', 'ai_output_tokens', 'ai_graded_at', 'review_requested_at',
            ]);
        });
    }
};
