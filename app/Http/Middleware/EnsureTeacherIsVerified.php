<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureTeacherIsVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('teacher')->user();

        if (!$user) {
            return redirect()->route('teacher.login');
        }

        if (!$user->teacherProfile?->is_verified) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Hesabınız hələ təsdiqlənməyib.'], 403);
            }

            return redirect()->route('teacher.awaiting-verification');
        }

        return $next($request);
    }
}
