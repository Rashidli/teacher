<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `auth`-dan sonra işləyir: hesabın müəllim rolu yoxdursa 403. */
class EnsureUserIsTeacher
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasRole('teacher')) {
            abort(403, 'Bu səhifəyə yalnız müəllimlər daxil ola bilər.');
        }

        return $next($request);
    }
}
