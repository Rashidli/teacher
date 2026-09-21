<?php

/*
| ŞƏRTLƏR VƏ QAYDALAR — ŞABLON MƏTN.
|
| Bu mətn nümunədir və hüquqşünas tərəfindən yoxlanmalıdır. Xüsusilə:
|   - [kvadrat mötərizədəki] yerlər (şirkət adı, VÖEN, ünvan, email, müddətlər) doldurulmalıdır;
|   - qanun istinadlarının adı, tarixi və nömrəsi rəsmi mənbə ilə tutuşdurulmalıdır;
|   - ödəniş və geri qaytarma şərtləri real biznes qaydalarına uyğunlaşdırılmalıdır.
| Rus versiyası (lang/ru/terms.php) eyni strukturdadır və eyni yoxlamanı tələb edir.
*/
return [
    'title' => 'İstifadə şərtləri və qaydalar',
    'meta_description' => 'İmtahan Platformasının istifadə şərtləri: hesab qaydaları, ödəniş və geri qaytarma, müəllif hüquqları və fərdi məlumatların emalı.',
    'updated' => 'Son yenilənmə: 19 sentyabr 2026',
    'intro' => 'Bu sənəd İmtahan Platformasından (bundan sonra — "Platforma") istifadə qaydalarını müəyyən edir. Platforma [şirkət adı, VÖEN] tərəfindən idarə olunur. Qeydiyyatdan keçməklə və ya Platformadan istifadə etməklə bu şərtləri qəbul etmiş olursan.',
    'toc' => 'Bölmələr',

    'sections' => [
        [
            'id' => 'istifade-sertleri',
            'title' => 'İstifadə şərtləri',
            'paragraphs' => [
                'Platforma imtahanlara hazırlıq üçün onlayn sınaq testləri təqdim edir. Platforma rəsmi imtahan təşkilatı deyil və Dövlət İmtahan Mərkəzi (DİM) ilə əlaqəli deyil.',
                'Sınaq testlərinin nəticələri yalnız hazırlıq səviyyəsini qiymətləndirmək üçündür və real imtahanda hər hansı nəticəyə zəmanət vermir.',
                '18 yaşına çatmamış istifadəçilər Platformadan valideynlərinin və ya digər qanuni nümayəndələrinin razılığı ilə istifadə edirlər.',
            ],
        ],
        [
            'id' => 'hesab-qaydalari',
            'title' => 'Hesab qaydaları',
            'paragraphs' => [
                'Qeydiyyat zamanı düzgün və aktual məlumat göstərməlisən. Hər şəxs yalnız bir hesab yarada bilər.',
            ],
            'items' => [
                'Parolun məxfiliyinə sən cavabdehsən. Hesabına başqasının daxil olduğunu düşünürsənsə, parolu dərhal dəyiş və bizə yaz.',
                'Hesab başqa şəxsə ötürülə, satıla və ya birgə istifadə oluna bilməz.',
                'Bu qaydaları pozan hesablar xəbərdarlıq edilmədən müvəqqəti və ya birdəfəlik bloklana bilər.',
            ],
        ],
        [
            'id' => 'odenis-ve-geri-qaytarma',
            'title' => 'Ödəniş və geri qaytarma',
            'paragraphs' => [
                'Platformada pulsuz və ödənişli sınaq testləri var. Qiymətlər Azərbaycan manatı (AZN) ilə göstərilir və ödənişdən əvvəl səhifədə aydın yazılır.',
                'Ödənişli testə giriş ödəniş təsdiqləndikdən sonra açılır.',
            ],
            'items' => [
                'Ödənişli test başlanmayıbsa, ödəniş tarixindən [X] gün ərzində geri qaytarma tələb edə bilərsən.',
                'Test başlanıbsa və ya nəticə göstərilibsə, ödəniş geri qaytarılmır. Platformanın texniki nasazlığı səbəbindən test tamamlana bilməyibsə, bu qayda tətbiq edilmir.',
                'Geri qaytarma sorğusu [email] ünvanına göndərilir və [X] iş günü ərzində baxılır. Vəsait ödənişin edildiyi üsulla qaytarılır.',
            ],
        ],
        [
            'id' => 'muellif-huquqlari',
            'title' => 'Məzmunun müəllif hüquqları',
            'paragraphs' => [
                'Platformadakı bütün suallar, cavab izahları, şəkillər, mətnlər və dizayn Platformaya və ya onun müəlliflərinə məxsusdur və "Müəllif hüququ və əlaqəli hüquqlar haqqında" Azərbaycan Respublikasının Qanunu ilə qorunur.',
                'Testlər yalnız şəxsi hazırlıq üçün istifadə oluna bilər. Aşağıdakılar qadağandır:',
            ],
            'items' => [
                'testləri, sualları və cavabları kopyalamaq, ekran görüntüsü və ya digər üsulla yaymaq;',
                'onları sosial şəbəkələrdə, messencerlərdə, saytlarda və ya çap şəklində paylaşmaq;',
                'onları satmaq, kurslarda və ya başqa kommersiya məqsədilə istifadə etmək;',
                'Platformadan avtomatlaşdırılmış vasitələrlə (bot, skript) məlumat toplamaq.',
            ],
            'closing' => 'Bu qadağanı pozan hesab bloklanır. Platforma qanunla nəzərdə tutulmuş qaydada zərərin ödənilməsini tələb etmək hüququnu saxlayır.',
        ],
        [
            'id' => 'ferdi-melumatlar',
            'title' => 'Fərdi məlumatların emalı',
            'paragraphs' => [
                'Fərdi məlumatlar "Fərdi məlumatlar haqqında" Azərbaycan Respublikasının Qanununa uyğun olaraq toplanır, emal olunur və qorunur.',
                'Toplanan məlumatlar: ad, soyad, email, mobil nömrə, sınaq nəticələri, həmçinin texniki məlumatlar (IP ünvanı, brauzer növü, cookie faylları).',
            ],
            'items' => [
                'Məqsəd: hesabın yaradılması və idarə olunması, nəticələrin saxlanması, bildirişlərin göndərilməsi, ödənişlərin icrası və Platformanın təhlükəsizliyi.',
                'Məlumatlar üçüncü şəxslərə satılmır və ötürülmür. İstisna: qanunla tələb olunan hallar və xidmətin göstərilməsi üçün zəruri olan tərəfdaşlar (məsələn, ödəniş sistemi, email xidməti).',
                'Məlumatlar hesab aktiv olduğu müddətdə, hesab silindikdən sonra isə qanunla tələb olunan müddət ərzində saxlanılır.',
                'Sən öz məlumatlarınla tanış olmaq, onlara düzəliş etmək və onların silinməsini tələb etmək hüququna maliksən. Bunun üçün [email] ünvanına yaz.',
            ],
        ],
        [
            'id' => 'mesuliyyet',
            'title' => 'Məsuliyyətin məhdudlaşdırılması',
            'paragraphs' => [
                'Platforma "olduğu kimi" təqdim olunur. Sualların real imtahan suallarına tam uyğunluğuna və real imtahanda müəyyən nəticəyə zəmanət verilmir.',
                'Texniki işlər, internet bağlantısı və ya üçüncü tərəf xidmətlərindəki nasazlıqlar səbəbindən Platforma müvəqqəti əlçatmaz ola bilər. Belə hallarda Platforma xidməti mümkün qədər tez bərpa etməyə çalışır.',
                'Qanunla icazə verilən həddə Platformanın məsuliyyəti istifadəçinin müvafiq xidmət üçün ödədiyi məbləğlə məhdudlaşır.',
            ],
        ],
        [
            'id' => 'deyisiklikler',
            'title' => 'Şərtlərin dəyişdirilməsi',
            'paragraphs' => [
                'Platforma bu şərtləri dəyişə bilər. Yeni versiya bu səhifədə dərc olunduğu gündən qüvvəyə minir, əhəmiyyətli dəyişikliklər barədə email ilə məlumat verilir.',
            ],
        ],
        [
            'id' => 'elaqe',
            'title' => 'Əlaqə',
            'paragraphs' => [
                'Sual, təklif və ya şikayət üçün bizimlə əlaqə saxla:',
            ],
            'items' => [
                'Email: [email]',
                'Telefon: [telefon]',
                'Ünvan: [hüquqi ünvan]',
            ],
        ],
    ],
];
