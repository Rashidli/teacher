<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `is_demo` bayrağı: `DemoContentSeeder`-in yaratdığı nümunə məzmunu real datadan ayırır.
 *
 * `php artisan demo:clear` yalnız bu bayraqlı sətirləri silir. Cəhdlər ayrıca bayraq almır —
 * onlar demo imtahana və ya demo şagirdə bağlılıqla tapılır, beləliklə real şagirdin demo
 * imtahandakı cəhdi də təmizlənir.
 */
return new class extends Migration
{
    private const TABLES = ['exams', 'questions', 'topics', 'users'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->boolean('is_demo')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // Sütunla birlikdə indeksi də gedir
                $blueprint->dropColumn('is_demo');
            });
        }
    }
};
