<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staging və lokal nüsxə axtarış sistemlərinə düşməsin.
 *
 * `APP_ENV=production` olmayan hər cavaba `X-Robots-Tag: noindex, nofollow` əlavə olunur —
 * staging-in `robots.txt`-i unudulsa belə nüsxə indeksləşmir (produksiyada heç nə dəyişmir).
 */
class PreventIndexingOutsideProduction
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->isProduction()) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
