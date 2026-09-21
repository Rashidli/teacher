<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TeacherOwnsSubject implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = auth()->user();

        // Admin hər fənni seçə bilər
        if ($user->hasRole('admin')) {
            return;
        }

        if (!$user->subjects()->where('subjects.id', $value)->exists()) {
            $fail('Bu fənn sizə aid deyil.');
        }
    }
}
