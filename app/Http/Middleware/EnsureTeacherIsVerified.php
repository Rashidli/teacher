<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `auth` + `teacher`-dən sonra işləyir: profil hələ təsdiqlənməyibsə gözləmə səhifəsi. */
class EnsureTeacherIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->teacherProfile?->is_verified) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Hesabınız hələ təsdiqlənməyib.'], 403);
            }

            return redirect()->route('teacher.awaiting-verification');
        }

        return $next($request);
    }
}
