<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `questions.question_image_alt` — sual şəklinin alternativ mətni.
 *
 * Şəkilli sualda (yol nişanı, sxem, qrafik) şəkil məzmunun ÖZÜDÜR, ona görə ekran
 * oxuyucusu üçün təsvir lazımdır. Mətn sualın cavabını verməməlidir: "üçbucaq nişan,
 * içində əyri ox" olar, "təhlükəli döngə nişanı" olmaz.
 *
 * Boş qalanda interfeys ümumi mətnə düşür ("Sual şəkli").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('question_image_alt', 255)->nullable()->after('question_image');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('question_image_alt');
        });
    }
};
