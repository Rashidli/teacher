<?php

namespace App\Http\Middleware;

use App\Support\Localization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sorğunun dilini müəyyən edir.
 *
 * - İctimai səhifələr: dil URL-dən gəlir (/ru/... → ru, prefikssiz → az) və sessiyada saxlanır.
 * - Panellər (prefikssiz, dil URL-də yoxdur): daxil olmuş istifadəçinin locale-i,
 *   yoxdursa sessiyadakı son dil, o da yoxdursa əsas dil.
 *
 * Brauzerin Accept-Language başlığına görə avtomatik yönləndirmə edilmir (SEO üçün).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Localization::isLocalizedRoute($request)) {
            $first = $request->segment(1);
            $locale = Localization::isSupported($first) ? $first : Localization::default();

            if ($request->hasSession()) {
                $request->session()->put('locale', $locale);
            }
        } else {
            $locale = $this->userLocale()
                ?? ($request->hasSession() ? $request->session()->get('locale') : null);
        }

        if (! Localization::isSupported($locale)) {
            $locale = Localization::default();
        }

        App::setLocale($locale);

        return $next($request);
    }

    private function userLocale(): ?string
    {
        return Auth::user()?->locale;
    }
}
