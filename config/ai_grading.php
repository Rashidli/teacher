<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Açıq yazılı cavabların avtomatik qiymətləndirilməsi
    |--------------------------------------------------------------------------
    |
    | YALNIZ `open_written` suallara tətbiq olunur. `open_coded` cavablar
    | `AnswerNormalizer` ilə onsuz da avtomatik yoxlanır və AI-yə göndərilmir.
    |
    | Açar `.env`-dədir (`ANTHROPIC_API_KEY`) və repoda SAXLANILMIR. Açar boşdursa
    | modul özünü söndürür: cavablar `pending_review` qalır, admin əl ilə qiymətləndirir.
    |
    */

    'enabled' => env('AI_GRADING_ENABLED', true),

    'api_key' => env('ANTHROPIC_API_KEY'),

    'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),

    'api_version' => '2023-06-01',

    /*
    | Model. Qısa açıq cavabın meyara görə qiymətləndirilməsi ağır düşünmə tələb
    | etmir, ona görə defolt Sonnet-dir. Esse üçün ayrıca (daha güclü) model təyin
    | etmək olar; `essay_model` boşdursa `model` işlənir.
    |
    | Qiymətlər (1M token): Sonnet 5 — $2 giriş / $10 çıxış, Opus 5 — $5 / $25.
    */
    'model' => env('AI_GRADING_MODEL', 'claude-sonnet-5'),

    'essay_model' => env('AI_GRADING_ESSAY_MODEL'),

    /*
    | Esse sayılan hədd: bu qədər simvoldan uzun cavab `essay_model` ilə qiymətləndirilir.
    */
    'essay_threshold_chars' => (int) env('AI_GRADING_ESSAY_THRESHOLD', 1200),

    /*
    | Cavab qısadır (qiymət + 1–2 cümlə), amma düşünmə tokenləri də bu limitə daxildir.
    */
    'max_tokens' => (int) env('AI_GRADING_MAX_TOKENS', 2000),

    /*
    | Düşünmə dərinliyi: meyar verilmiş qiymətləndirmə üçün "medium" kifayətdir.
    | low | medium | high | xhigh | max
    */
    'effort' => env('AI_GRADING_EFFORT', 'medium'),

    /*
    | Şagirdin cavabı göndərilməzdən əvvəl bu uzunluğa qədər kəsilir. Kəsildiyi
    | promptda AÇIQ yazılır — model natamam mətni tam sanıb aşağı qiymət verməsin.
    */
    'max_answer_chars' => (int) env('AI_GRADING_MAX_ANSWER_CHARS', 6000),

    /*
    | Sualın və meyarın uzunluq limiti (çox uzun mətn xərci artırır).
    */
    'max_prompt_chars' => (int) env('AI_GRADING_MAX_PROMPT_CHARS', 4000),

    'timeout' => (int) env('AI_GRADING_TIMEOUT', 60),

    'tries' => (int) env('AI_GRADING_TRIES', 3),

    /*
    | Növbənin adı. `queue:work --queue=ai-grading,default` ilə uyğun olmalıdır.
    */
    'queue' => env('AI_GRADING_QUEUE', 'ai-grading'),

    /*
    | DİM şkalası: modelin qaytara biləcəyi YEGANƏ qiymətlər. Başqa dəyər gələrsə
    | cavab rədd olunur və sual əl ilə yoxlamaya qalır.
    |
    | Açar modelə göndərilir (mətn), dəyər isə `attempt_answers.grade_ratio`-ya yazılır.
    */
    'scale' => [
        '0' => 0,
        '1/3' => 1 / 3,
        '1/2' => 1 / 2,
        '2/3' => 2 / 3,
        '1' => 1,
    ],

];
