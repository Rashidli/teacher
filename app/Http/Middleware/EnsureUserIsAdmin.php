<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('admin')->user();

        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Bu səhifəyə giriş icazəniz yoxdur.');
        }

        return $next($request);
    }
}
