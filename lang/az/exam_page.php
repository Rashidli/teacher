<?php

// İctimai imtahan səhifəsi (Exam/Show.vue). İmtahan adı və izahı DB-dədir.
return [
    'about' => 'İmtahan haqqında',
    'sections' => 'Bölmələr',
    'section' => 'Bölmə',
    'questions' => 'sual',
    'minutes' => 'dəqiqə',
    'duration' => 'Müddət',
    'question_count' => 'Sual sayı',
    'max_score' => 'Maksimal bal',
    'price' => 'Qiymət',
    'free' => 'Pulsuz',
    'kind' => 'Növ',
    'kinds' => [
        'general' => 'Ümumi sınaq',
        'topic_trial' => 'Mövzu sınağı',
        'subject' => 'Fənn sınağı',
        'practice' => 'Məşq testi',
    ],
    'quarter' => ':number-ci rüb',

    'start' => 'Başla',
    'buy' => 'Al',
    'continue' => 'Davam et',
    'continue_hint' => 'Davam edən cəhdin var, :minutes dəqiqə qalıb.',
    'start_hint' => 'Düyməni basanda cəhd başlayır və taymer işə düşür.',
    'guest_hint' => 'Başlamaq üçün hesabına daxil ol — imtahan bu səhifədə açılacaq.',
    'buy_hint' => 'Ödənişdən sonra imtahan dərhal açılır.',
    'purchases_closed' => 'Onlayn ödəniş tezliklə aktivləşəcək.',
    'last_result' => 'Son nəticəyə bax',

    // Test rejimi (PAYMENT_DRIVER=fake): real ödəniş getmir
    'test_mode' => 'Test rejimi — real ödəniş getmir.',
    'test_mode_hint' => 'Sayt sınaq mərhələsindədir: "Al" basanda ödəniş dərhal təsdiqlənir və imtahan açılır.',
    'test_mode_paid' => 'Test rejimi: ödəniş təsdiqləndi, imtahan açıldı. Real ödəniş getmədi.',
];
