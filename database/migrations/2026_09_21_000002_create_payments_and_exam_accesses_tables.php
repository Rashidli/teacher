<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alış və imtahana giriş hüququ.
 *
 * payments polimorfdur (purchasable_type/purchasable_id): indi yalnız imtahan satılır, amma
 * fənn paketi, qrup paketi və abunə gələndə ödəniş cədvəli dəyişməyəcək.
 *
 * exam_accesses "bu şagird bu imtahanı aça bilər" faktıdır. Mənbə ödəniş də ola bilər,
 * adminin əl ilə verdiyi icazə də (köçürmə ilə ödəyənlər üçün).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Nə satılır: hazırda Exam, sonra paket/abunə
            $table->morphs('purchasable');

            // Alış anındakı qiymət: imtahanın qiyməti sonra dəyişsə də ödəniş tarixçəsi düz qalır
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('AZN');

            $table->string('status', 20)->default('pending');
            $table->string('provider', 50);

            // Bankın əməliyyat nömrəsi: təkrar callback-ları tanımaq üçün unikaldır
            $table->string('provider_ref', 191)->nullable()->unique();

            // Bankdan gələn cavab (kart məlumatı təmizlənmiş halda)
            $table->json('payload')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('exam_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();

            // payment | manual | free
            $table->string('source', 20);
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            // null = müddətsiz
            $table->timestamp('expires_at')->nullable();

            // null = limitsiz cəhd. Sonra "bir alış = bir cəhd" modelinə keçmək üçün sxem dəyişməyəcək.
            $table->unsignedInteger('attempts_allowed')->nullable();

            // Əl ilə veriləndə: kim verdi və hansı əsasla (köçürmə qəbzi, tarix və s.)
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();

            // Ləğv sətri silmir: qeyd və audit izi qalır (refunded ödəniş də bunu doldurur)
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            // Bir şagirdə bir imtahan üçün bir sətir: təkrar alışda mövcud sətir yenilənir
            $table->unique(['user_id', 'exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_accesses');
        Schema::dropIfExists('payments');
    }
};
