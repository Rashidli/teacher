<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fənn mövzuları. Rüb üzrə mövzu sınağı (Mərhələ 4) bunun üzərində qurulacaq.
 *
 * `quarter` nullable-dır: sürücülük və dövlət qulluğu mövzularının rübü yoxdur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug', 120);

            // 1–4: məktəb rübləri. null: rübə bağlı olmayan mövzular.
            $table->unsignedTinyInteger('quarter')->nullable();

            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['subject_id', 'slug']);
            $table->index(['subject_id', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
