<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Produksiyada rollar (admin/teacher/student) həmişə mövcuddur — RoleSeeder onları
     * quraşdırma zamanı yaradır. Testdə baza hər dəfə sıfırdan qurulduğu üçün
     * assignRole() rol tapmayıb xəta verirdi: hər test bazası ilə birlikdə rolları da yükləyirik.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed(RoleSeeder::class);
        }
    }
}
