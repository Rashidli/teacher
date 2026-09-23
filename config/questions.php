<?php

use App\Models\Question;

return [

    /*
    |--------------------------------------------------------------------------
    | Kateqoriya üzrə icazəli sual tipləri
    |--------------------------------------------------------------------------
    |
    | Hər imtahan növünün öz formatı var: sürücülük imtahanında açıq sual yoxdur,
    | MİQ-də yalnız qapalı test verilir, abituriyent II mərhələsində isə hər üç tip
    | işlənir. Bu siyahı həm admin sual formasında, həm bankdan generasiyada, həm də
    | nümunə məzmun seeder-ində tətbiq olunur.
    |
    | Açar kateqoriyanın `path` sahəsidir. Uyğunluq ƏN UZUN PREFİKSƏ görədir:
    | "dovlet-qullugu/tam-sinaq/bb-ac" öz sətrini tapmasa "dovlet-qullugu"-a düşür,
    | o da olmasa "*" (defolt) işləyir.
    |
    | Mənbə: DİM və müvafiq qurumların imtahan formatları (23.09.2026 araşdırması).
    |
    */

    'allowed_types' => [

        // Defolt: hər üç tip (buraxılış, I mərhələ, digər)
        '*' => [
            Question::TYPE_MULTIPLE_CHOICE,
            Question::TYPE_OPEN_CODED,
            Question::TYPE_OPEN_WRITTEN,
        ],

        // Buraxılış imtahanı: qapalı + kodlaşdırılan + yazılı
        'mekteb' => [
            Question::TYPE_MULTIPLE_CHOICE,
            Question::TYPE_OPEN_CODED,
            Question::TYPE_OPEN_WRITTEN,
        ],

        // Abituriyent: I mərhələ və II mərhələ (22 qapalı + 5 kodlaşdırılan + 3 yazılı)
        'abituriyent' => [
            Question::TYPE_MULTIPLE_CHOICE,
            Question::TYPE_OPEN_CODED,
            Question::TYPE_OPEN_WRITTEN,
        ],

        // Magistratura: qapalı + kodlaşdırılan, esse ayrıca yazılır (95 + 5 bal)
        'magistratura' => [
            Question::TYPE_MULTIPLE_CHOICE,
            Question::TYPE_OPEN_CODED,
            Question::TYPE_OPEN_WRITTEN,
        ],

        // Dövlət qulluğu: qapalı + yazılı açıq (2 bal) + esse
        'dovlet-qullugu' => [
            Question::TYPE_MULTIPLE_CHOICE,
            Question::TYPE_OPEN_WRITTEN,
        ],

        // BB və AC qrupları: 100 qapalı sual, açıq hissə yoxdur
        'dovlet-qullugu/tam-sinaq/bb-ac' => [
            Question::TYPE_MULTIPLE_CHOICE,
        ],

        // Müəllimlər (sertifikasiya, diaqnostik, məktəbəqədər, direktor müsabiqəsi): yalnız qapalı
        'muellimler' => [
            Question::TYPE_MULTIPLE_CHOICE,
        ],

        // MİQ ağacda "muellimler"in altındadır, amma `path` sütunu sayəsində URL-i "/miq"-dir
        'miq' => [
            Question::TYPE_MULTIPLE_CHOICE,
        ],

        // Sürücülük nəzəri imtahanı: yalnız qapalı (şəkilli) suallar
        'suruculuk-imtahani' => [
            Question::TYPE_MULTIPLE_CHOICE,
        ],

    ],

];
