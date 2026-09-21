<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * İmtahan bölmələri: çoxfənli imtahan hər fənn üçün bir bölmədən ibarətdir.
 *
 * İKİ KOD YOLU SAXLANILMIR: mövcud tək-fənli imtahanların hər birinə bir bölmə yaradılır və
 * `section_id` sonra NOT NULL olur. Beləliklə bal hesablaması, cəhd səhifəsi və nəticə səhifəsi
 * hər imtahan üçün eyni məntiqlə işləyir.
 *
 * `max_score` nullable-dır: qrupa bağlı imtahanlarda bal `subject_group_scores`-dan gəlir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            $table->string('title')->nullable();
            $table->unsignedSmallInteger('question_count')->nullable();
            $table->decimal('max_score', 6, 2)->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['exam_id', 'subject_id']);
            $table->index(['exam_id', 'order']);
        });

        Schema::table('exam_question', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->after('exam_id')
                ->constrained('exam_sections')->cascadeOnDelete();
        });

        Schema::table('attempt_questions', function (Blueprint $table) {
            // Dondurulmuş siyahı bölmə üzrə də qorunur
            $table->foreignId('section_id')->nullable()->after('attempt_id')
                ->constrained('exam_sections')->nullOnDelete();
        });

        $this->backfillSections();

        Schema::table('exam_question', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable(false)->change();
        });
    }

    /** Mövcud imtahanların hər birinə öz fənni ilə bir bölmə yaradılır. */
    private function backfillSections(): void
    {
        DB::table('exams')->orderBy('id')->chunk(100, function ($exams) {
            foreach ($exams as $exam) {
                $questionCount = DB::table('exam_question')->where('exam_id', $exam->id)->count();

                $sectionId = DB::table('exam_sections')->insertGetId([
                    'exam_id' => $exam->id,
                    'subject_id' => $exam->subject_id,
                    'question_count' => $questionCount ?: null,
                    'order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('exam_question')->where('exam_id', $exam->id)
                    ->update(['section_id' => $sectionId]);

                DB::table('attempt_questions')
                    ->whereIn('attempt_id', DB::table('exam_attempts')->where('exam_id', $exam->id)->pluck('id'))
                    ->update(['section_id' => $sectionId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('attempt_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
        });

        Schema::table('exam_question', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
        });

        Schema::dropIfExists('exam_sections');
    }
};
