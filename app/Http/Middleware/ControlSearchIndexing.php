<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saytın axtarış sistemlərinə düşməsinə nəzarət.
 *
 * İndeksləşmə yalnız iki şərt birlikdə ödənəndə açıqdır:
 *   - `SEO_INDEXING=true` (config/seo.php) və
 *   - `APP_ENV=production`.
 *
 * Əks halda hər cavaba `X-Robots-Tag: noindex, nofollow` əlavə olunur. Eyni qərar
 * `<meta name="robots">` teqinə (Blade və SeoHead.vue) və `robots.txt`-ə də tətbiq olunur
 * (RobotsController) — üç yerdə eyni mənbədən, `Seo::indexable()`.
 */
class ControlSearchIndexing
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! Seo::indexable()) {
            $response->headers->set('X-Robots-Tag', Seo::robotsDirective());
        }

        return $response;
    }
}
