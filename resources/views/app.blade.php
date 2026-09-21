<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#FCFCFA">

        <!-- Fonts: Literata (başlıqlar) və Onest (mətn): latın (ə, ğ, ı, İ, ş, ç, ö, ü) və kiril tam dəstəklənir -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Literata:opsz,wght@7..72,500..700&family=Onest:wght@400..700&subset=latin,latin-ext,cyrillic,cyrillic-ext&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead

        {{-- SSR yoxdursa: title, description, canonical, hreflang serverdə Blade ilə --}}
        @if (empty($__inertiaSsrResponse))
            <title inertia>{{ __('site.brand') }}</title>
            @include('partials.seo')
        @endif
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
