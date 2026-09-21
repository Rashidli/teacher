<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Tədris sektoru (az/ru) — interfeys dilindən AYRI anlayışdır.
 *
 * Kimin hansı məzmunu görməsi:
 *  - Daxil olmuş istifadəçi → öz `users.sector` dəyəri (URL-dən asılı deyil).
 *  - Qonaq → sessiyadakı seçim, yoxdursa URL dilinə görə defolt (/ru → ru).
 */
class Sector
{
    public const AZ = 'az';

    public const RU = 'ru';

    public const ALL = [self::AZ, self::RU];

    public const SESSION_KEY = 'sector';

    public static function isValid(?string $sector): bool
    {
        return in_array($sector, self::ALL, true);
    }

    /** Cari sorğu üçün aktiv sektor */
    public static function current(?Request $request = null): string
    {
        $user = self::authenticatedUser();

        if ($user && self::isValid($user->sector)) {
            return $user->sector;
        }

        $request ??= request();
        $chosen = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        return self::isValid($chosen) ? $chosen : self::defaultForLocale();
    }

    /** Qonaq üçün defolt: interfeys dili rusdursa rus sektoru */
    public static function defaultForLocale(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === self::RU ? self::RU : self::AZ;
    }

    /** Daxil olmuş istifadəçi sektoru dəyişə bilməz (profildən dəyişir) */
    public static function guestCanSwitch(): bool
    {
        return self::authenticatedUser() === null;
    }

    private static function authenticatedUser()
    {
        foreach (['student', 'admin', 'teacher'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return Auth::guard($guard)->user();
            }
        }

        return null;
    }
}
