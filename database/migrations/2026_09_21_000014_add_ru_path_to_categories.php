<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rus dilindəki kateqoriya ünvanı: `/ru/abiturient/1-y-qrup` kimi.
 *
 * `path` sütunu kimi `ru_path` da AÇIQ saxlanılır (valideyn zəncirindən hesablanmır):
 * ünvan sabit qalır, MİQ kimi kökə çıxarılmış düyünlər isə öz yolunu saxlaya bilir.
 * Boş olanda səhifə Azərbaycan yolu ilə açılır — tərcümə edilməmiş düyün itmir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('categories', 'ru_path')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->string('ru_path')->nullable()->unique()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['ru_path']);
            $table->dropColumn('ru_path');
        });
    }
};
