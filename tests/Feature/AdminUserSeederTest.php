<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_when_credentials_are_not_configured(): void
    {
        config(['seeding.admin.email' => null, 'seeding.admin.password' => null]);

        $this->expectException(RuntimeException::class);

        $this->seed(AdminUserSeeder::class);
    }

    public function test_it_fails_when_only_the_email_is_configured(): void
    {
        config(['seeding.admin.email' => 'admin@example.com', 'seeding.admin.password' => '']);

        $this->expectException(RuntimeException::class);

        $this->seed(AdminUserSeeder::class);
    }

    public function test_it_creates_an_admin_from_the_configured_credentials(): void
    {
        config([
            'seeding.admin.email' => 'admin@example.com',
            'seeding.admin.password' => 'S3cret-from-env',
        ]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('S3cret-from-env', $admin->password));
    }

    /** Seeder təsadüfən yenidən işlədiləndə mövcud adminin parolu sıfırlanmamalıdır. */
    public function test_it_does_not_overwrite_an_existing_admin_password(): void
    {
        $existing = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('original-password'),
        ]);

        config([
            'seeding.admin.email' => 'admin@example.com',
            'seeding.admin.password' => 'different-password',
        ]);

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
        $this->assertTrue(Hash::check('original-password', $existing->refresh()->password));
    }
}
