<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Support\Localization;
use App\Support\Seo;
use Illuminate\Http\Request;
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
        // Tək guard: kimin daxil olduğunu route prefiksi deyil, sessiya müəyyən edir.
        // Panel seçimi frontend-də rollara görə aparılır (bir hesabın bir neçə rolu ola bilər).
        $user = $request->user();

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
                    // Tədris sektoru (interfeys dilindən ayrıdır) — profil formasında lazımdır
                    'sector' => $user->sector,
                    'roles' => $user->roles->pluck('name'),
                ] : null,
            ],
            // Dil (SetLocale middleware-i tərəfindən müəyyən olunur) və SEO (canonical, hreflang)
            'locale' => fn () => app()->getLocale(),
            'seo' => fn () => Localization::seo($request),
            // SEO_INDEXING bağlıdırsa hər səhifə noindex meta teqi alır (SeoHead.vue)
            'indexable' => fn () => Seo::indexable(),
            // SSR serverində route() üçün (brauzerdə @routes istifadə olunur)
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'features' => [
                'teachers' => (bool) config('features.teachers'),
            ],
            // Ödəniş test rejimi (PAYMENT_DRIVER=fake): interfeysdə xəbərdarlıq göstərilir
            'payments' => [
                'test_mode' => (string) config('payments.driver') === 'fake',
            ],
            // Kök kateqoriyalar: ana səhifə və altlıq hər səhifədə işlədir.
            // Lazy (closure) — yalnız istifadə olunanda sorğu gedir.
            'categories' => fn () => Category::active()->roots()
                ->orderBy('order')
                ->get(['id', 'name', 'short', 'path', 'ru_path', 'translations'])
                ->map(fn (Category $category) => [
                    'path' => $category->pathFor(),
                    'name' => $category->localized('name'),
                    'short' => $category->localized('short'),
                    'url' => $category->urlFor(),
                ]),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
