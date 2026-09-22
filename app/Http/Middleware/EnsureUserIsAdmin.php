<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `auth`-dan sonra işləyir: hesabın admin rolu yoxdursa 403. */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasRole('admin')) {
            abort(403, 'Bu səhifəyə giriş icazəniz yoxdur.');
        }

        return $next($request);
    }
}
