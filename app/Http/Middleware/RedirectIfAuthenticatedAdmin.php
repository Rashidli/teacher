<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticatedAdmin
{
    /**
     * Admin üçün xüsusi guest middleware.
     * Yalnız admin rollu user admin guard-da authenticated olduqda redirect edir.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('admin')->check()) {
            $user = Auth::guard('admin')->user();

            // Yalnız admin rollu istifadəçiləri redirect et
            if ($user && $user->hasRole('admin')) {
                return redirect()->route('admin.dashboard');
            }

            // Admin deyilsə, guard-dan çıxart
            Auth::guard('admin')->logout();
        }

        return $next($request);
    }
}
