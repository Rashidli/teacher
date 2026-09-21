<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rus sektoru (tədris dili).
 *
 * `users.sector` interfeys dilindən (`users.locale`) AYRIDIR: rus sektorunda oxuyan şagird
 * interfeysi Azərbaycanca saxlaya bilər və əksinə.
 *
 * - `questions.language`: hər sual bir dildədir; ru sualları az sualının avtomatik tərcüməsi
 *   deyil, ayrıca yazılır. `translation_group_id` eyni sualın iki versiyasını bağlayır.
 * - `exams.sector`: imtahan bir sektora aiddir və yalnız həmin dildə suallar qəbul edir.
 * - `category_subject.sector`: ana dili fənni sektora görə dəyişir (az → Azərbaycan dili,
 *   ru → Rus dili). null = hər iki sektor üçün.
 * - `categories.ru_enabled`: məzmun hazır olmayan kateqoriyada ru seçimi gizlədilir.
 *
 * Hər addım öz şərti ilə qorunur: MySQL-də DDL geri qaytarılmadığı üçün yarımçıq qalmış
 * icra təkrar işlədiləndə "column already exists" ilə dayanmasın.
 */
return new class extends Migration
{
    private const OLD_UNIQUE = 'category_subject_category_id_subject_id_unique';

    private const NEW_UNIQUE = 'category_subject_category_id_subject_id_sector_unique';

    public function up(): void
    {
        if (! Schema::hasColumn('users', 'sector')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('sector', 2)->default('az')->after('locale');
            });
        }

        if (! Schema::hasColumn('questions', 'language')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->string('language', 2)->default('az')->after('type');
                // Eyni sualın digər dildəki versiyası ilə əlaqə (statistika və redaktə üçün)
                $table->unsignedBigInteger('translation_group_id')->nullable()->after('language');
                $table->index(['subject_id', 'language']);
                $table->index('translation_group_id');
            });
        }

        if (! Schema::hasColumn('exams', 'sector')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->string('sector', 2)->default('az')->after('kind');
            });
        }

        if (! Schema::hasColumn('categories', 'ru_enabled')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->boolean('ru_enabled')->default(false)->after('has_exams');
            });
        }

        if (! Schema::hasColumn('category_subject', 'sector')) {
            Schema::table('category_subject', function (Blueprint $table) {
                // null = hər iki sektor
                $table->string('sector', 2)->nullable()->after('subject_id');
            });
        }

        /*
         * Əvvəlcə yeni unikal indeks yaradılır, sonra köhnəsi silinir: `category_id` hər an
         * indeksin ilk sütunu olaraq qalır və xarici açar indekssiz qalmır (MySQL 1553).
         */
        if (! Schema::hasIndex('category_subject', self::NEW_UNIQUE)) {
            Schema::table('category_subject', function (Blueprint $table) {
                $table->unique(['category_id', 'subject_id', 'sector'], self::NEW_UNIQUE);
            });
        }

        if (Schema::hasIndex('category_subject', self::OLD_UNIQUE)) {
            Schema::table('category_subject', function (Blueprint $table) {
                $table->dropUnique(self::OLD_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('category_subject', self::OLD_UNIQUE)) {
            Schema::table('category_subject', function (Blueprint $table) {
                $table->unique(['category_id', 'subject_id'], self::OLD_UNIQUE);
            });
        }

        if (Schema::hasIndex('category_subject', self::NEW_UNIQUE)) {
            Schema::table('category_subject', function (Blueprint $table) {
                $table->dropUnique(self::NEW_UNIQUE);
            });
        }

        Schema::table('category_subject', function (Blueprint $table) {
            $table->dropColumn('sector');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('ru_enabled');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('sector');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['subject_id', 'language']);
            $table->dropIndex(['translation_group_id']);
            $table->dropColumn(['language', 'translation_group_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sector');
        });
    }
};
