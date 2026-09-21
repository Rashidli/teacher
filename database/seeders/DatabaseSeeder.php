<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SubjectSeeder::class,
            GroupSeeder::class,
            SubjectGroupScoreSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
