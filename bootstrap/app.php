<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SEO_INDEXING=false və ya produksiyadan kənar nüsxə: axtarış sistemlərinə düşməsin
        $middleware->append(\App\Http\Middleware\ControlSearchIndexing::class);

        $middleware->web(append: [
            // Dil HandleInertiaRequests-dən əvvəl müəyyən olunmalıdır (paylaşılan props dilə bağlıdır)
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Bank callback-ı bizim formadan gəlmir: CSRF tokeni olmur, imza ilə yoxlanılır
        $middleware->validateCsrfTokens(except: [
            'payments/callback/*',
        ]);

        // Rol middleware-ləri: hamısı tək "web" guard-ı ilə işləyir, `auth`-dan SONRA yazılır
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'teacher' => \App\Http\Middleware\EnsureUserIsTeacher::class,
            'teacher.verified' => \App\Http\Middleware\EnsureTeacherIsVerified::class,
            'student' => \App\Http\Middleware\EnsureUserIsStudent::class,
            // /admin/login: daxil olmuş şagird də formanı görə bilsin
            'guest.admin' => \App\Http\Middleware\RedirectAdminsToDashboard::class,
        ]);

        // Qonaq səhifəsinə daxil olmamış istifadəçi hansı giriş formasına göndərilir
        // Şagird və ümumi hesab səhifələri üçün cari dildəki /login (və ya /ru/login)
        $middleware->redirectGuestsTo(fn ($request) => match(true) {
            $request->routeIs('admin.*') => route('admin.login'),
            $request->routeIs('teacher.*') => route('teacher.login'),
            default => \App\Support\Localization::route('login'),
        });

        // Daxil olmuş istifadəçi /login, /register, /admin/login açarsa öz panelinə gedir
        $middleware->redirectUsersTo(fn ($request) => \App\Support\Panel::homeUrl($request->user()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
