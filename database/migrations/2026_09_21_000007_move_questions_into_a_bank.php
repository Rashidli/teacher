<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Suallar imtahandan ayrılıb sual bankına keçir.
 *
 * Əvvəl: `questions.exam_id` — sual bir imtahana aid idi və təkrar istifadə oluna bilmirdi.
 * İndi: sual fənnə (və mövzuya) aiddir, imtahanla əlaqə `exam_question` pivotundadır.
 * Sıra da suala yox, imtahandakı yerinə aiddir — ona görə `questions.order` pivota keçir.
 *
 * DAVRANIŞ DƏYİŞİKLİYİ: imtahan silinəndə sualları artıq silinmir, yalnız bağlantı kəsilir.
 *
 * Geri qaytarma: `exam_id` bərpa olunur və pivotdakı İLK imtahan yazılır. Bir sual birdən çox
 * imtahana bağlanıbsa, geri qaytarmada yalnız birinci bağlantı qalır (köhnə sxem çox bağlantını
 * saxlaya bilmir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Köçürmə zamanı doldurulur, sonra məcburi edilir
            $table->foreignId('subject_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->after('subject_id')->constrained()->nullOnDelete();

            // easy | medium | hard
            $table->string('difficulty', 10)->default('medium')->after('type');
            $table->string('source')->nullable()->after('explanation');
        });

        Schema::create('exam_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['exam_id', 'question_id']);
            $table->index(['exam_id', 'order']);
        });

        // Mövcud suallar: fənn imtahandan götürülür, bağlantı pivota köçürülür
        DB::table('questions')
            ->join('exams', 'exams.id', '=', 'questions.exam_id')
            ->orderBy('questions.id')
            ->select('questions.id', 'questions.exam_id', 'questions.order', 'exams.subject_id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('questions')->where('id', $row->id)
                        ->update(['subject_id' => $row->subject_id]);

                    DB::table('exam_question')->insert([
                        'exam_id' => $row->exam_id,
                        'question_id' => $row->id,
                        'order' => $row->order,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_id');
            $table->dropColumn('order');
        });

        // Fənn artıq məcburidir: bankdakı hər sual bir fənnə aiddir
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('exam_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->integer('order')->default(0)->after('explanation');
        });

        // Hər sual üçün pivotdakı ilk imtahan
        DB::table('exam_question')->orderBy('id')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('questions')
                    ->where('id', $row->question_id)
                    ->whereNull('exam_id')
                    ->update(['exam_id' => $row->exam_id, 'order' => $row->order]);
            }
        });

        // Heç bir imtahana bağlı olmayan bank sualları köhnə sxemdə saxlanıla bilmir
        DB::table('questions')->whereNull('exam_id')->delete();

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('exam_id')->nullable(false)->change();
        });

        Schema::dropIfExists('exam_question');

        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('topic_id');
            $table->dropConstrainedForeignId('subject_id');
            $table->dropColumn(['difficulty', 'source']);
        });
    }
};
