<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticatedTeacher
{
    /**
     * Teacher üçün xüsusi guest middleware.
     * Yalnız teacher rollu user teacher guard-da authenticated olduqda redirect edir.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('teacher')->check()) {
            $user = Auth::guard('teacher')->user();

            // Yalnız teacher rollu istifadəçiləri redirect et
            if ($user && $user->hasRole('teacher')) {
                // Verified olub-olmadığına bax
                if (!$user->teacherProfile?->is_verified) {
                    return redirect()->route('teacher.awaiting-verification');
                }
                return redirect()->route('teacher.dashboard');
            }

            // Teacher deyilsə, guard-dan çıxart
            Auth::guard('teacher')->logout();
        }

        return $next($request);
    }
}
