<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * İlkin admin hesabı. Email və parol yalnız .env-dən gəlir (ADMIN_SEED_EMAIL,
 * ADMIN_SEED_PASSWORD) — repoda heç bir giriş məlumatı saxlanılmır.
 *
 * Hesab artıq varsa parolu DƏYİŞMİR: seeder təsadüfən yenidən işlədiləndə
 * mövcud adminin parolu sıfırlanmasın.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) config('seeding.admin.email'));
        $password = (string) config('seeding.admin.password');

        if ($email === '' || $password === '') {
            throw new RuntimeException(
                'AdminUserSeeder: .env faylında ADMIN_SEED_EMAIL və ADMIN_SEED_PASSWORD təyin edilməlidir. '
                .'(config:cache işlədilibsə əvvəlcə `php artisan config:clear` və ya yenidən `config:cache`.)'
            );
        }

        $existing = User::where('email', $email)->first();

        if ($existing) {
            $existing->assignRole(Role::findOrCreate('admin', 'web'));

            $this->command?->warn("Admin artıq mövcuddur ({$email}) — parol dəyişdirilmədi.");

            return;
        }

        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $admin->assignRole(Role::findOrCreate('admin', 'web'));
    }
}
