<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kateqoriya ağacı (iyerarxik, sonsuz dərinlik).
 *
 * `groups` cədvəli bal hesablaması üçün ayrıca qalır və burada TƏKRARLANMIR: abituriyent
 * qrup düyünləri `group_id` ilə mövcud qruplara bağlanır. Beləliklə bal matrisi
 * (`subject_group_scores`) tək mənbə olaraq qalır.
 *
 * `path` ayrıca sütundur, çünki URL iyerarxiyadan asılı olmamalıdır: MİQ ağacda
 * "Müəllimlər"in altındadır, amma ünvanı `/miq` olaraq qalır (mövcud slug qorunur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();

            // Bal üçün mövcud qrupa bağlantı (yalnız abituriyent düyünlərində dolur)
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();

            $table->string('slug', 80);
            $table->string('path', 255)->unique();

            $table->string('name');
            $table->string('short')->nullable();
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);
            // false: yalnız məlumat səhifəsi (V qrup, "Digər" düyünləri)
            $table->boolean('has_exams')->default(true);
            $table->integer('order')->default(0);

            // SEO mətnləri admin paneldən redaktə olunur
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('h1')->nullable();
            $table->text('intro')->nullable();

            // Digər dillərdəki mətnlər: {"ru": {"name": "...", "short": "...", "seo_title": "..."}}
            // Mərhələ 5-də (rus sektoru) genişlənəcək.
            $table->json('translations')->nullable();

            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
            $table->index(['parent_id', 'order']);
        });

        Schema::create('category_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('question_count')->nullable();
            $table->unsignedTinyInteger('options_per_question')->nullable();

            // null: kateqoriyanın qrupu varsa bal subject_group_scores-dan götürülür
            $table->decimal('max_score', 6, 2)->nullable();

            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'subject_id']);
        });

        Schema::table('exams', function (Blueprint $table) {
            // İmtahan ən dəqiq düyünə bağlanır (məs. abituriyent/1-ci-qrup)
            $table->foreignId('category_id')->nullable()->after('group_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('category_subject');
        Schema::dropIfExists('categories');
    }
};
