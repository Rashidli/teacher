<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_group_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 4, 2);
            $table->timestamps();
            $table->unique(['subject_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_group_scores');
    }
};
