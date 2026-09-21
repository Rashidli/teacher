<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sual tipləri MVP üçün üçə çıxır:
 *   - multiple_choice — variantlı test (variant sayı imtahan səviyyəsində: 4 və ya 5)
 *   - open_coded      — qısa/rəqəm cavab, avtomatik yoxlanır
 *   - open_written    — həll yazılır, admin əl ilə qiymətləndirir (köhnə "open_ended")
 *
 * questions.type enum-dan string-ə keçir: MariaDB-də enum dəyişmək cədvəli hər dəfə yenidən
 * qurur, sətir sütunu isə yeni tip əlavə etməyi migrationsuz mümkün edir (validasiya app
 * tərəfindədir — Question::TYPES).
 *
 * accepted_answers yalnız open_coded üçündür və rəqəmi olmayan alternativlər saxlayır
 * ("x=2" kimi). Rəqəm cavabları AnswerNormalizer ilə ədədi müqayisə olunur, ona görə
 * adminin "0,5 / 0.5 / 1/2" variantlarını ayrıca yazmasına ehtiyac yoxdur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('type', 32)->default('multiple_choice')->change();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->json('accepted_answers')->nullable()->after('type');
        });

        DB::table('questions')->where('type', 'open_ended')->update(['type' => 'open_written']);

        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedTinyInteger('options_per_question')->default(5)->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('options_per_question');
        });

        // Köhnə sxemdə yalnız iki tip var: hər iki açıq tip open_ended-ə qayıdır
        // (open_coded sualların accepted_answers məlumatı itir).
        DB::table('questions')->whereIn('type', ['open_written', 'open_coded'])
            ->update(['type' => 'open_ended']);

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('accepted_answers');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->enum('type', ['multiple_choice', 'open_ended'])
                ->default('multiple_choice')
                ->change();
        });
    }
};
