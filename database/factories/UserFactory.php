<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * "name" sütunu qəsdən verilmir: User::booted() onu first_name + last_name-dən doldurur.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => self::uniquePhone(),
            'locale' => 'az',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * +994XXXXXXXXX — bazadakı unikal indekslə uyğun (users.phone unique).
     */
    protected static function uniquePhone(): string
    {
        return '+994'.fake()->unique()->numerify('#########');
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /** Telefonsuz hesab (admin və köhnə istifadəçilər): unikal indeks NULL-a mane olmur. */
    public function withoutPhone(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->withoutPhone()->withRole('admin');
    }

    public function teacher(): static
    {
        return $this->withRole('teacher');
    }

    public function student(): static
    {
        return $this->withRole('student');
    }

    /**
     * Rol RoleSeeder ilə gəlir, amma testlərdə baza boş olur: yoxdursa yaradılır.
     * User::$guard_name = 'web' olduğu üçün rol da "web" guard-ında olmalıdır.
     */
    protected function withRole(string $role): static
    {
        return $this->afterCreating(function ($user) use ($role) {
            $user->assignRole(Role::findOrCreate($role, 'web'));
        });
    }
}
