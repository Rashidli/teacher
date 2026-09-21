<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsStudent
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('student')->user();

        if (!$user || !$user->hasRole('student')) {
            abort(403, 'Bu səhifəyə yalnız şagirdlər daxil ola bilər.');
        }

        return $next($request);
    }
}
