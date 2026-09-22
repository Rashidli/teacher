<?php

// Ana səhifə (Welcome.vue, TutorsSection.vue)
return [
    'hero' => [
        'title' => 'İmtahana onlayn sınaq testləri ilə hazırlaş',
        'lead' => 'Abituriyent, məktəbli, magistratura, dövlət qulluğu, MİQ və sürücülük imtahanları üçün real formatda testlər. Vaxt gedir, nəticə dərhal hesablanır, səhv etdiyin mövzular ayrıca göstərilir.',
        'sample_link' => 'Nümunə suala bax',
    ],

    'card' => [
        'label' => 'İmtahan cavab kartı',
        'code' => 'Kod',
        'question' => 'Hansı imtahana hazırlaşırsan?',
        'hint' => 'Seç və pulsuz sınaq testinə başla.',
        'go' => 'Başla',
    ],

    'exams' => [
        'title' => 'Hər imtahanın öz formatı var',
        'lead' => 'Sual sayı, fənlər və vaxt limiti seçdiyin imtahana uyğundur. Hazırlığın hansı mərhələsindəsənsə, oradan başla.',
        'action' => 'Sınaq testinə başla',

        'education' => [
            'title' => 'Təhsil yolu',
            'note' => 'Məktəbdən ali məktəbə, oradan magistraturaya',
            'school' => '5–11-ci siniflər üçün fənn testləri, 9-cu və 11-ci sinif buraxılış imtahanlarına hazırlıq.',
            'applicant' => 'Qəbul imtahanı formatında tam sınaqlar: qapalı və açıq suallar, fənlər üzrə bal hesablanması.',
            'groups_label' => 'İxtisas qrupları',
            'master' => 'Magistraturaya qəbul üçün məntiq, informatika və xarici dil blokları.',
        ],

        'career' => [
            'title' => 'Peşə imtahanları',
            'note' => 'Dövlət qulluğuna və müəllimliyə qəbul',
            'civil_name' => 'Dövlət qulluğu',
            'civil_text' => 'Dövlət qulluğuna qəbulun test mərhələsi.',
            'civil_tags' => ['Qanunvericilik', 'Məntiq', 'İnformatika'],
            'miq_name' => 'MİQ',
            'miq_text' => 'Müəllimlərin işə qəbulu, fənn ixtisasları üzrə.',
            'miq_tags' => ['İxtisas', 'Metodika', 'Məntiq'],
        ],

        'driving' => [
            'title' => 'Sürücülük vəsiqəsi',
            'sign_alt' => 'Xəbərdarlıq yol nişanı: üçbucaq, qırmızı haşiyə, ortada nida işarəsi',
            'name' => 'Nəzəri imtahan',
            'text' => 'Yol hərəkəti qaydaları üzrə suallar, yol nişanları və vəziyyət şəkilləri ilə.',
            'tags' => ['Yol nişanları', 'Vəziyyət şəkilləri', 'Qaydalar'],
        ],
    ],

    'how' => [
        'title' => 'Sınaq real imtahan kimi keçir',
        'steps' => [
            ['title' => 'İmtahanı seç', 'text' => 'Sual sayı, fənlərin ardıcıllığı və vaxt limiti həmin imtahandakı kimidir.'],
            ['title' => 'Vaxta qarşı işlə', 'text' => 'Sayğac ekranın yuxarısında görünür. Vaxt bitəndə sınaq avtomatik tamamlanır, verdiyin cavablar itmir.'],
            ['title' => 'Nəticəni dərhal gör', 'text' => 'Bal, düzgün, səhv və boş cavabların sayı, sınağa sərf etdiyin vaxt.'],
            ['title' => 'Zəif mövzularını tap', 'text' => 'Səhv cavablar mövzulara görə qruplaşdırılır. Növbəti hazırlığa haradan başlayacağını bilirsən.'],
        ],
        'result' => [
            'caption' => 'Nəticə səhifəsindən nümunə',
            'title' => 'Riyaziyyat, sınaq 4',
            'correct' => 'Düzgün',
            'wrong' => 'Səhv',
            'empty' => 'Boş',
            'time' => 'Vaxt',
            'topics_title' => 'Mövzular üzrə düzgün cavablar',
            'weak' => 'Zəif mövzu',
            'topics' => [
                'percent' => 'Faizlər və nisbət',
                'equations' => 'Tənliklər',
                'functions' => 'Funksiyalar',
                'geometry' => 'Həndəsə',
                'logarithm' => 'Loqarifm',
            ],
        ],
    ],

    'sample' => [
        'title' => 'Sual ekranda belə görünür',
        'lead' => 'Hər sualda qalan vaxt, sualın nömrəsi və variantlar görünür. Sürücülük suallarında vəziyyət şəkli sualın içindədir.',
        'prev' => 'Əvvəlki',
        'next' => 'Növbəti',
        'chosen' => '(seçilib)',
        'math' => [
            'subject' => 'Riyaziyyat',
            'question' => 'Əgər 3x − 7 = 11 olarsa, x² − 2x ifadəsinin qiyməti neçədir?',
            'saved' => 'Cavabın yadda saxlanıldı',
            'caption' => 'Test sualı: variantlar A–E, cavab seçiləndə dərhal yadda saxlanılır.',
        ],
        'drive' => [
            'subject' => 'Sürücülük, nəzəri',
            'scene_alt' => 'Nizamlanmayan bərabərhüquqlu yolların kəsişməsi: göy avtomobil cənubdan, qırmızı avtomobil şərqdən kəsişməyə yaxınlaşır, hər ikisi düz hərəkət edir',
            'question' => 'Nizamlanmayan kəsişmədə hansı avtomobil birinci keçməlidir?',
            'options' => ['Göy avtomobil', 'Qırmızı avtomobil', 'Hər ikisi eyni vaxtda'],
            'caption' => 'Sürücülük sualı: vəziyyət şəkli və üç cavab variantı.',
        ],
    ],

    'tutors' => [
        'title' => 'Şagirdlərinə testi A4 vərəqində yox, onlayn ver',
        'lead' => 'Repetitorlar üçün: sualları bir dəfə yığ, testi bir şagirdə və ya bütün qrupa təyin et. Şagirdlər telefondan işləyir, yoxlamanı platforma edir.',
        'register' => 'Repetitor kimi qeydiyyatdan keç',
        'compare_caption' => 'Testi A4 kağızda və platformada keçirməyin müqayisəsi',
        'paper' => 'A4 kağızda',
        'online' => 'Platformada',
        'rows' => [
            ['label' => 'Hazırlıq', 'paper' => 'Hər qrup üçün çap və surət', 'online' => 'Sualları bir dəfə yığırsan'],
            ['label' => 'Paylama', 'paper' => 'Dərsdə əl ilə', 'online' => 'Şagirdə və ya qrupa təyin'],
            ['label' => 'Yoxlama', 'paper' => 'Hər vərəqi özün yoxlayırsan', 'online' => 'Avtomatik, test bitən kimi'],
            ['label' => 'Nəticələr', 'paper' => 'Dəftərdə qeyd', 'online' => 'Hər şagird üzrə cədvəl'],
            ['label' => 'Zəif mövzular', 'paper' => 'Özün hesablayırsan', 'online' => 'Mövzulara görə göstərilir'],
        ],
    ],
];
