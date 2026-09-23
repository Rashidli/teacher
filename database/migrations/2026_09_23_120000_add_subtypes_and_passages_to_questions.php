<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DİM-in açıq tapşırıq növləri.
 *
 * DİM-də açıq tapşırıqlar iki qrupdur:
 *  (a) KODLAŞDIRILAN — variantlar verilir, cavab kodlaşdırılır və avtomatik yoxlanılır:
 *      hesablama, seçim (bir neçə düzgün variant), ardıcıllıq, uyğunluq;
 *  (b) YAZILI — variantsız, meyarlarla qiymətləndirilir.
 *
 * `subtype` hər iki qrupda işlənir: kodlaşdırılanda YOXLAMA QAYDASINI seçir, yazılıda isə
 * yalnız məlumat və filtr üçündür (bal qaydası dəyişmir).
 *
 * `pairs` yalnız uyğunluq sualında doludur: sol-sağ cütlər SIRALI saxlanılır (1-ci sol
 * 1-ci sağa uyğundur), şagird tərəfdə sağ sütun qarışdırılır.
 *
 * `passage_id` mətn/mənbə əsaslı suallar üçündür: bir mətnə bir neçə sual bağlana bilər.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passages', function (Blueprint $table) {
            $table->id();
            $table->string('language', 2)->default('az')->index();
            $table->string('title');
            $table->text('body');
            // Mənbə əsaslı tapşırıqda sənədin mənbəyi ("Tarix dərsliyi, IX sinif")
            $table->string('source')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->string('subtype', 20)->nullable()->after('type')->index();
            $table->json('pairs')->nullable()->after('accepted_answers');
            $table->foreignId('passage_id')->nullable()->after('topic_id')
                ->constrained('passages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['passage_id']);
            $table->dropColumn(['subtype', 'pairs', 'passage_id']);
        });

        Schema::dropIfExists('passages');
    }
};
