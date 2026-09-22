<?php

return [

    /*
    | Müəllim/repetitor modulu. MVP-də söndürülüb: route-lar qeydiyyatdan keçmir (404),
    | UI-dakı müəllim linkləri və bölmələri gizlədilir. Kod və baza olduğu kimi qalır.
    */
    'teachers' => (bool) env('FEATURE_TEACHERS', false),

];
