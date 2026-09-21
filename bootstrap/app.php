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

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'teacher' => \App\Http\Middleware\EnsureUserIsTeacher::class,
            'teacher.verified' => \App\Http\Middleware\EnsureTeacherIsVerified::class,
            'student' => \App\Http\Middleware\EnsureUserIsStudent::class,
            'guest.admin' => \App\Http\Middleware\RedirectIfAuthenticatedAdmin::class,
            'guest.teacher' => \App\Http\Middleware\RedirectIfAuthenticatedTeacher::class,
            'guest.student' => \App\Http\Middleware\RedirectIfAuthenticatedStudent::class,
        ]);

        // Configure guest middleware redirect for each guard
        // Şagird və ümumi hesab səhifələri üçün cari dildəki /login (və ya /ru/login)
        $middleware->redirectGuestsTo(fn ($request) => match(true) {
            $request->routeIs('admin.*') => route('admin.login'),
            $request->routeIs('teacher.*') => route('teacher.login'),
            default => \App\Support\Localization::route('login'),
        });

        // Configure authenticated user redirect for guest pages
        $middleware->redirectUsersTo(fn ($request) => match(true) {
            $request->routeIs('admin.*') => route('admin.dashboard'),
            $request->routeIs('teacher.*') => route('teacher.dashboard'),
            // Daxil olmuş şagird /login, /register və s. açarsa, panelinə gedir
            default => route('student.dashboard'),
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
