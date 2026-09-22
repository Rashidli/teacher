<?php

namespace App\Support;

use App\Models\User;

/**
 * Rola görə panel seçimi.
 *
 * Bir hesabın bir neçə rolu ola bilər: girişdən sonra ən yüksək səlahiyyətli panel açılır
 * (admin → müəllim → şagird). Müəllim modulu söndürülübsə müəllim paneli yoxdur — yalnız
 * müəllim rolu olan hesabın gedəcəyi yer qalmır, o da "panel yoxdur" səhifəsinə düşür.
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

        if (self::teacherPanelIsOpen($user)) {
            return $user->teacherProfile?->is_verified
                ? route('teacher.dashboard')
                : route('teacher.awaiting-verification');
        }

        if ($user->hasRole('student')) {
            return route('student.dashboard');
        }

        return route('no-panel');
    }

    /** Hesabın girə biləcəyi panel varmı? */
    public static function hasPanel(?User $user): bool
    {
        return $user !== null && (
            $user->hasRole('admin')
            || $user->hasRole('student')
            || self::teacherPanelIsOpen($user)
        );
    }

    private static function teacherPanelIsOpen(User $user): bool
    {
        return (bool) config('features.teachers') && $user->hasRole('teacher');
    }
}
