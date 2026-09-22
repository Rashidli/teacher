<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `auth`-dan sonra işləyir: hesabın şagird rolu yoxdursa 403. */
class EnsureUserIsStudent
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasRole('student')) {
            abort(403, 'Bu səhifəyə yalnız şagirdlər daxil ola bilər.');
        }

        return $next($request);
    }
}
