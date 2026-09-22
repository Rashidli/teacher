<?php

namespace App\Support;

use App\Models\User;

/**
 * Rola görə panel seçimi.
 *
 * Bir hesabın bir neçə rolu ola bilər: girişdən sonra ən yüksək səlahiyyətli panel açılır
 * (admin → müəllim → şagird). Müəllim modulu söndürülübsə müəllim paneli yoxdur.
 */
class Panel
{
    /** Girişdən (və ya qonaq səhifəsinə daxil olmuş istifadəçinin yönləndirilməsindən) sonrakı ünvan */
    public static function homeUrl(?User $user): string
    {
        if (! $user) {
            return Localization::route('login');
        }

        if ($user->hasRole('admin')) {
            return route('admin.dashboard');
        }

        if (config('features.teachers') && $user->hasRole('teacher')) {
            return $user->teacherProfile?->is_verified
                ? route('teacher.dashboard')
                : route('teacher.awaiting-verification');
        }

        return route('student.dashboard');
    }
}
