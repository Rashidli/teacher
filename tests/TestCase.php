<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * İCTİMAİ DİSK HƏMİŞƏ SAXTADIR.
         *
         * Testlər produksiya qovluğunda işləyir (ayrıca mühit yoxdur — bax ROADMAP P2.5),
         * ona görə real fayl sisteminə toxunan bir test bütün sayta zərər verə bilər:
         * bir dəfə `demo:clear` çağıran test produksiyadakı yol nişanı şəkillərini sildi
         * və imtahan səhifəsində bütün şəkillər 404 verdi.
         *
         * Defolt burada qoyulur ki, hər yeni testdə ayrıca yazmaq lazım gəlməsin. Test
         * real diskə ehtiyac duyursa, bunu ÖZÜ açıq şəkildə etməlidir.
         */
        Storage::fake('public');

        /*
         * Produksiyada rollar (admin/teacher/student) həmişə mövcuddur — RoleSeeder onları
         * quraşdırma zamanı yaradır. Testdə baza hər dəfə sıfırdan qurulduğu üçün
         * assignRole() rol tapmayıb xəta verirdi: hər test bazası ilə birlikdə rolları da yükləyirik.
         */
        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed(RoleSeeder::class);
        }
    }
}
