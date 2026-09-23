<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `categories.color` — kök bölmənin rəngi (#RRGGBB).
 *
 * Kataloq kartlarında üst zolaq və kateqoriya nişanı bu rəngdədir. Rəng YALNIZ kök
 * düyünlərdə saxlanılır; alt düyünlər onu ağacdan miras alır (`Category::color()`),
 * beləliklə bir bölmənin bütün imtahanları eyni rənglə tanınır.
 *
 * Admin paneldən dəyişilir. Defolt dəyərlər `CategorySeeder`-dədir və kontrastları
 * WCAG AA-ya görə yoxlanılıb (kağız fonunda ən aşağı 5.0:1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
