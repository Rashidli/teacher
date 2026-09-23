<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İmtahan etiketləri (çox-çoxa).
 *
 * Kateqoriya ağacı imtahanın NƏ OLDUĞUNU bildirir (abituriyent II qrup, sürücülük …),
 * etiket isə ona ortoqonal əlamətlər verir. Ən vacibi SİNİF SƏVİYYƏSİDİR: eyni fənn
 * imtahanı 5-ci və 9-cu sinif üçün ayrı-ayrı ola bilər, kateqoriya isə eynidir.
 *
 * `kind` etiket qrupudur — kataloqda filtrlər qrup üzrə göstərilir (əvvəl "Sinif",
 * sonra digərləri). `order` eyni qrup daxilində sıranı saxlayır (2-ci sinif … 11-ci).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            // 'grade' — sinif səviyyəsi, 'other' — sərbəst etiket
            $table->string('kind', 20)->default('other')->index();
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exam_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_tag');
        Schema::dropIfExists('tags');
    }
};
