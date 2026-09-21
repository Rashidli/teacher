<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cəhdin bölmə üzrə nəticəsi.
 *
 * Dəyərlər bal hesablandığı anda DONDURULUR: `max_score` və nisbi bal (NB) burada saxlanılır,
 * ona görə qrupun bal matrisi sonradan dəyişsə də köhnə nəticələr dəyişmir.
 *
 * Bölmə silinsə sətir qalır (`section_id` null olur) — `subject_id` və `title` surəti ilə
 * nəticə səhifəsi yenə düzgün göstərilir. Fənn üzrə statistika (Mərhələ 7) buradan çıxır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('exam_sections')->nullOnDelete();
            $table->foreignId('subject_id')->constrained();

            // Hesablama anındakı surət
            $table->string('title')->nullable();
            $table->decimal('max_score', 6, 2);

            $table->unsignedSmallInteger('question_count')->default(0);
            $table->unsignedSmallInteger('correct_answers')->default(0);
            $table->unsignedSmallInteger('wrong_answers')->default(0);
            $table->unsignedSmallInteger('unanswered')->default(0);

            /** DİM-in 100 ballıq nisbi balı */
            $table->decimal('relative_score', 5, 2)->default(0);
            /** Fənn balı: NB × max_score / 100 */
            $table->decimal('subject_score', 6, 2)->default(0);

            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['attempt_id', 'order']);
            $table->index(['subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_sections');
    }
};
