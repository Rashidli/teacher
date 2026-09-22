<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '+994551234567',
            'password' => 'password',
            'password_confirmation' => 'password',
            'sector' => 'az',
            'terms' => true,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('student.dashboard', absolute: false));

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('student'));
        // "name" formada soruşulmur, ad və soyaddan yığılır
        $this->assertSame('Test User', $user->name);
    }

    /** Frontend-ə güvənilmir: nömrə serverdə +994XXXXXXXXX formatına gətirilir. */
    public function test_phone_number_is_normalized_before_it_is_stored(): void
    {
        $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'phone@example.com',
            'phone' => '(055) 123-45-67',
            'password' => 'password',
            'password_confirmation' => 'password',
            'sector' => 'az',
            'terms' => true,
        ])->assertSessionHasNoErrors()->assertRedirect(route('student.dashboard', absolute: false));

        $this->assertSame('+994551234567', User::where('email', 'phone@example.com')->value('phone'));
    }

    public function test_terms_must_be_accepted(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'noterms@example.com',
            'phone' => '+994551234567',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('terms');
        $this->assertGuest();
    }

    public function test_phone_number_must_be_unique(): void
    {
        User::factory()->student()->create(['phone' => '+994551234567']);

        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'duplicate@example.com',
            'phone' => '+994551234567',
            'password' => 'password',
            'password_confirmation' => 'password',
            'sector' => 'az',
            'terms' => true,
        ]);

        $response->assertSessionHasErrors('phone');
    }
}
