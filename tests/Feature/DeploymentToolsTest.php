<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Deploy alətləri: nüsxə və anonimləşdirmə. Produksiyaya toxunmamalıdırlar.
 * İndeksləşmə bayrağı ayrıca yoxlanılır: SearchIndexingTest.
 */
class DeploymentToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymize_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);

        $student = User::factory()->student()->create(['email' => 'real@example.com']);

        $this->artisan('staging:anonymize')->assertFailed();

        $this->assertSame('real@example.com', $student->fresh()->email);
    }

    public function test_anonymize_replaces_student_data_but_keeps_admins(): void
    {
        $student = User::factory()->student()->create([
            'email' => 'real@example.com',
            'first_name' => 'Rəşid',
        ]);
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->artisan('staging:anonymize', ['--password' => 'sinaq-parol'])
            ->expectsConfirmation(
                'Bazadakı bütün şagird və müəllim məlumatları dəyişdiriləcək. Davam edilsin?',
                'yes'
            )
            ->assertSuccessful();

        $student->refresh();

        $this->assertSame('user'.$student->id.'@staging.local', $student->email);
        $this->assertSame('Test', $student->first_name);
        $this->assertTrue(Hash::check('sinaq-parol', $student->password));

        // Admin hesabı toxunulmur: staging-ə giriş üçün lazımdır
        $this->assertSame('admin@example.com', $admin->fresh()->email);
    }
}
