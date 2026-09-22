<?php

use App\Support\Slug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * exams.slug — ictimai imtahan səhifəsinin ünvanı (`/imtahan/{slug}`).
 *
 * Mövcud imtahanlar üçün başlıqdan qurulur; təkrar olanda "-2", "-3" əlavə olunur.
 * Unikal indeks silinmiş imtahanları da əhatə edir (softDeletes) — silinmiş imtahanın
 * ünvanı yenidən işlənsə, köhnə keçidlər başqa imtahana düşərdi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('slug', 180)->nullable()->after('id');
        });

        $taken = [];

        foreach (DB::table('exams')->orderBy('id')->get(['id', 'title']) as $exam) {
            $slug = Slug::unique(
                (string) $exam->title,
                fn (string $candidate) => isset($taken[$candidate]),
            );

            $taken[$slug] = true;

            DB::table('exams')->where('id', $exam->id)->update(['slug' => $slug]);
        }

        Schema::table('exams', function (Blueprint $table) {
            $table->string('slug', 180)->nullable(false)->change();
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
