<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - locale: istifadəçinin dili (az|ru). Email-lər bu dildə göndərilir (User::preferredLocale).
 * - phone: +994XXXXXXXXX formatında, unikal. NULL dəyərlər unikal indeksə mane olmur
 *   (admin və telefonsuz köhnə hesablar).
 *
 * first_name və last_name artıq 2026_01_13_220001_add_fields_to_users_table ilə mövcuddur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('az')->after('phone');
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('locale');
        });
    }
};
