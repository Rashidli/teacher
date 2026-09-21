{{--
    schema.org strukturlaşdırılmış məlumatı. Yalnız serverdə yazılır: axtarış robotu hər
    səhifəni ayrıca yükləyir, ona görə SPA keçidində yenilənməsinə ehtiyac yoxdur.
    Məlumatı səhifənin controller-i sorğu atributunda qoyur (CategoryController::shareSeo).
--}}
@php($jsonLd = request()->attributes->get(\App\Support\Localization::JSON_LD_ATTRIBUTE))
@if ($jsonLd)
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endif
