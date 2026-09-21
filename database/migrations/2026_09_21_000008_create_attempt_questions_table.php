<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cəhdin sual siyahısı DONDURULUR.
 *
 * Suallar bankda paylaşıldığı üçün imtahanın sual dəsti sonradan dəyişə bilər (sual ayrılır,
 * kopyası ilə əvəzlənir, yenisi əlavə olunur). Köhnə nəticə səhifəsi bundan təsirlənməməlidir,
 * ona görə cəhd başlayanda həmin andakı suallar bura yazılır.
 *
 * Cəhd səhifəsi, bal hesablaması, cavabsız sayı və nəticə səhifəsi imtahanın indiki
 * suallarından yox, bu siyahıdan işləyir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            // Sual silinmir: cəhddə işlənmiş sual bankdan silinə bilmir (yalnız imtahandan ayrılır)
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
            $table->index(['attempt_id', 'order']);
        });

        // Mövcud cəhdlər: həmin imtahanın cari sualları ilə doldurulur
        DB::table('exam_attempts')->orderBy('id')->chunk(100, function ($attempts) {
            foreach ($attempts as $attempt) {
                $questions = DB::table('exam_question')
                    ->where('exam_id', $attempt->exam_id)
                    ->orderBy('order')
                    ->get(['question_id', 'order']);

                foreach ($questions as $question) {
                    DB::table('attempt_questions')->insert([
                        'attempt_id' => $attempt->id,
                        'question_id' => $question->question_id,
                        'order' => $question->order,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_questions');
    }
};
