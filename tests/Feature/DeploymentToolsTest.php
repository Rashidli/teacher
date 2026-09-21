<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Staging/deploy alətləri: indeksləşmə qoruması və anonimləşdirmə.
 * Bunlar produksiyaya toxunmamalıdır — testlər məhz bunu yoxlayır.
 */
class DeploymentToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_production_responses_are_not_indexable(): void
    {
        // Test mühiti "testing"-dir, yəni produksiya deyil
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_production_responses_keep_no_robots_header(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);

        $response = $this->get('/');

        $this->assertNull($response->headers->get('X-Robots-Tag'));
    }

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
