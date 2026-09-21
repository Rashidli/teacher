<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bankdan imtahan generasiyası üçün lazım olan sahələr.
 *
 * - `topics.parent_id`: mövzu qrupları (məsələn "Tarix" fənnində "Azərbaycan tarixi" və
 *   "Ümumi tarix" qrupları, onların altında isə real mövzular).
 * - `subjects.is_language`: xarici dillər generasiya formasında tək seçim (radio) kimi
 *   göstərilir — bir imtahana yalnız bir dil düşür.
 * - `exams.kind/quarter/is_cumulative`: şagird tərəfindəki "qrup → mövzu sınağı → rüb" axını
 *   imtahanın hansı rübə aid olduğunu bilməlidir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('subject_id')
                ->constrained('topics')->cascadeOnDelete();
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->boolean('is_language')->default(false)->after('category');
        });

        Schema::table('exams', function (Blueprint $table) {
            // general | topic_trial | subject | practice
            $table->string('kind', 20)->default('general')->after('category_id');
            $table->unsignedTinyInteger('quarter')->nullable()->after('kind');
            $table->boolean('is_cumulative')->default(false)->after('quarter');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['kind', 'quarter', 'is_cumulative']);
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('is_language');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
