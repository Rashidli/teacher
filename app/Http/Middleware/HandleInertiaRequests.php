<?php

namespace App\Http\Middleware;

use App\Support\Localization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = null;
        $guard = null;
        $teachersEnabled = (bool) config('features.teachers');

        // Route prefix-ə əsasən müvafiq guard-ı yoxla
        if ($request->is('admin/*') || $request->is('admin')) {
            if (Auth::guard('admin')->check()) {
                $user = Auth::guard('admin')->user();
                if ($user && $user->hasRole('admin')) {
                    $guard = 'admin';
                } else {
                    $user = null;
                }
            }
        } elseif ($teachersEnabled && ($request->is('teacher/*') || $request->is('teacher'))) {
            if (Auth::guard('teacher')->check()) {
                $user = Auth::guard('teacher')->user();
                if ($user && $user->hasRole('teacher')) {
                    $guard = 'teacher';
                } else {
                    $user = null;
                }
            }
        } elseif ($request->is('student/*') || $request->is('student')) {
            if (Auth::guard('student')->check()) {
                $user = Auth::guard('student')->user();
                if ($user && $user->hasRole('student')) {
                    $guard = 'student';
                } else {
                    $user = null;
                }
            }
        } else {
            // Digər route-lar üçün (profile və s.) - hər hansı authenticated guard
            // Müəllim modulu söndürülüb olanda köhnə müəllim sessiyası nəzərə alınmır
            $guards = $teachersEnabled ? ['admin', 'teacher', 'student'] : ['admin', 'student'];
            foreach ($guards as $g) {
                if (Auth::guard($g)->check()) {
                    $user = Auth::guard($g)->user();
                    $guard = $g;
                    break;
                }
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'name' => $user->name,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'roles' => $user->roles->pluck('name'),
                ] : null,
                'guard' => $guard,
            ],
            // Dil (SetLocale middleware-i tərəfindən müəyyən olunur) və SEO (canonical, hreflang)
            'locale' => fn () => app()->getLocale(),
            'seo' => fn () => Localization::seo($request),
            // SSR serverində route() üçün (brauzerdə @routes istifadə olunur)
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'features' => [
                'teachers' => $teachersEnabled,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
