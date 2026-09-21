<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'name' => 'Admin User',
            'email' => 'admin@teacher.cvhazirla.az',
            'password' => Hash::make('[silindi]'),
            'is_active' => true,
        ]);

        $admin->assignRole('admin');
    }
}
