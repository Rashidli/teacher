{{--
    SSR serveri cavab vermədikdə ehtiyat SEO teqləri (SeoHead.vue ilə eyni).
    "inertia" atributu sayəsində brauzerdə Inertia bunları SeoHead-in teqləri ilə əvəz edir.
--}}
@php($seo = \App\Support\Localization::seo(request()))
<meta name="description" content="{{ __('site.seo.description') }}" inertia="description">
@if ($seo)
    <link rel="canonical" href="{{ $seo['canonical'] }}" inertia="canonical">
    @foreach ($seo['alternates'] as $lang => $url)
        <link rel="alternate" hreflang="{{ $lang }}" href="{{ $url }}" inertia="hreflang-{{ $lang }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $seo['x_default'] }}" inertia="hreflang-x-default">
@endif
