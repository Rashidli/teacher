<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'purchasable_type' => (new Exam)->getMorphClass(),
            'purchasable_id' => Exam::factory(),
            'amount' => 10.00,
            'currency' => 'AZN',
            'status' => Payment::STATUS_PENDING,
            'provider' => 'fake',
        ];
    }

    public function forExam(Exam $exam): static
    {
        return $this->state(fn () => [
            'purchasable_type' => $exam->getMorphClass(),
            'purchasable_id' => $exam->id,
            'amount' => $exam->price,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }
}
