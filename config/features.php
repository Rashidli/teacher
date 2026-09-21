<?php

return [

    /*
    | Müəllim/repetitor modulu. MVP-də söndürülüb: route-lar qeydiyyatdan keçmir (404),
    | UI-dakı müəllim linkləri və bölmələri gizlədilir. Kod və baza olduğu kimi qalır.
    */
    'teachers' => (bool) env('FEATURE_TEACHERS', false),

    /*
    | Müəllim modulu söndürülüb olanda admin tərəfindən yaradılan imtahanların sahibi
    | (exams.teacher_id). Admin hesabının ID-si.
    */
    'exam_owner_id' => env('EXAM_OWNER_ID') ? (int) env('EXAM_OWNER_ID') : null,

];
