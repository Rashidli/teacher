<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticatedStudent
{
    /**
     * Student üçün xüsusi guest middleware.
     * Yalnız student rollu user student guard-da authenticated olduqda redirect edir.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('student')->check()) {
            $user = Auth::guard('student')->user();

            // Yalnız student rollu istifadəçiləri redirect et
            if ($user && $user->hasRole('student')) {
                return redirect()->route('student.dashboard');
            }

            // Student deyilsə, guard-dan çıxart
            Auth::guard('student')->logout();
        }

        return $next($request);
    }
}
