<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Dil prefiksli URL-lər və route adları üçün köməkçi.
 *
 * İctimai route-lar iki dəfə qeydiyyatdan keçir: az üçün prefikssiz ("login"),
 * digər dillər üçün "{locale}." adı və "/{locale}" prefiksi ilə ("ru.login").
 */
class Localization
{
    public static function default(): string
    {
        return config('locales.default');
    }

    public static function supported(): array
    {
        return config('locales.supported');
    }

    public static function isSupported(?string $locale): bool
    {
        return in_array($locale, static::supported(), true);
    }

    /** Verilən dil üçün route adı: login → ru.login */
    public static function routeName(string $name, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === static::default()) {
            return $name;
        }

        return Route::has("{$locale}.{$name}") ? "{$locale}.{$name}" : $name;
    }

    /** Cari (və ya verilən) dildə route URL-i */
    public static function route(string $name, mixed $parameters = [], bool $absolute = true, ?string $locale = null): string
    {
        return route(static::routeName($name, $locale), $parameters, $absolute);
    }

    /** Route dil prefiksli ictimai qrupa aiddirmi (routes/web.php-də 'localized' => true) */
    public static function isLocalizedRoute(Request $request): bool
    {
        return (bool) $request->route()?->getAction('localized');
    }

    /** URL yolundan dil prefiksini çıxarır: "ru/qaydalar" → "qaydalar", "ru" → "" */
    public static function stripPrefix(string $path): string
    {
        $path = trim($path, '/');
        $segments = explode('/', $path);

        if (static::isSupported($segments[0]) && $segments[0] !== static::default()) {
            array_shift($segments);
        }

        return implode('/', $segments);
    }

    /** Verilən dildə eyni səhifənin tam URL-i (query string-siz) */
    public static function urlFor(Request $request, string $locale): string
    {
        $path = static::stripPrefix($request->path());

        if ($locale !== static::default()) {
            $path = trim($locale.'/'.$path, '/');
        }

        return url($path === '' ? '/' : $path);
    }

    /**
     * Canonical və hreflang məlumatı. Yalnız dil prefiksli ictimai səhifələr üçün,
     * digərləri (panellər) üçün null.
     */
    public static function seo(Request $request): ?array
    {
        if (! static::isLocalizedRoute($request)) {
            return null;
        }

        $alternates = [];
        foreach (static::supported() as $locale) {
            $alternates[$locale] = static::urlFor($request, $locale);
        }

        return [
            'canonical' => $alternates[app()->getLocale()],
            'alternates' => $alternates,
            'x_default' => $alternates[static::default()],
        ];
    }
}
