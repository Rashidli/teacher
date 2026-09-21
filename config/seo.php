<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Axtarış sistemləri üçün indeksləşmə
    |--------------------------------------------------------------------------
    |
    | false olanda sayt TAM bağlanır: hər səhifə `noindex, nofollow` meta teqi və
    | `X-Robots-Tag` başlığı alır, `robots.txt` isə `Disallow: /` qaytarır və
    | sitemap-ı göstərmir. Bu, `APP_ENV`-dən asılı deyil — hazır olmayan nüsxə
    | (indiki müvəqqəti domen, staging, lokal) təsadüfən indeksləşməsin deyə.
    |
    | Əsl domenə keçəndə `SEO_INDEXING=true` qoyulur.
    |
    */

    'indexing' => env('SEO_INDEXING', false),

];
