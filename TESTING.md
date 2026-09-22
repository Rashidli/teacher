# Əl ilə yoxlama siyahısı

Bu siyahı canlı saytda (https://teacher.cvhazirla.az) əl ilə keçirilən yoxlama üçündür.
Hər bəndin yanında **gözlənilən nəticə** yazılıb — fərqli nəticə görsən, qeyd et.

Avtomatik testlər bu axınların çoxunu onsuz da yoxlayır (`php artisan test` — 355 test),
buradakı məqsəd interfeysin real brauzerdə davranışıdır.

**Yoxlamadan əvvəl:** `php artisan db:backup` (test datası yaradacaqsan).

---

## A. Admin paneli

Giriş: `/admin/login` (admin rolu olmayan hesab bura buraxılmır; 5 uğursuz cəhddən sonra
müvəqqəti bloklanır). Admin vahid `/login`-dən də daxil ola bilər — panelə eyni cür düşür.

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| A0.1 | Şagird hesabı ilə `/admin/dashboard` aç | **403** (giriş səhifəsinə yönləndirmə yox) |
| A0.2 | Admin hesabı ilə `/login`-dən daxil ol | Admin panelinə düşür |
| A0.3 | `/admin/login`-də yanlış parolla 5 dəfə cəhd et, sonra düzgün parol yaz | "Bir neçə saniyədən sonra…" mesajı, giriş açılmır |

### A1. Sual yaratma (əl ilə)

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| A1.1 | İmtahan seç → "Sual əlavə et" | Forma açılır, bölmə seçimi imtahanın bölmələrini göstərir |
| A1.2 | Test sualı: mətn + 4 (və ya 5) variant, biri düzgün → Yadda saxla | Sual əlavə olunur, imtahan səhifəsində görünür. **Bu əvvəllər sınırdı** — variantlı sual saxlanmırdı, indi işləməlidir |
| A1.3 | Düzgün cavab seçmədən yadda saxla | "Düz bir düzgün cavab seçilməlidir" xətası, sual yaranmır |
| A1.4 | Variant sayını imtahandakından fərqli ver (məs. 3) | "Bu imtahanda hər sualda N variant olmalıdır" xətası |
| A1.5 | Qısa cavablı sual (`open_coded`): qəbul olunan cavab `0,5` | Saxlanılır. Şagird `0.5`, `0,5`, `1/2` yazanda düzgün sayılır (AnswerNormalizer) |
| A1.6 | Qısa cavablı sualda cavab siyahısını boş burax | "Ən azı bir düzgün cavab yazılmalıdır" xətası |
| A1.7 | Açıq (yazılı) sual yarat | Saxlanılır; şagird cavab yazır, bal admin yoxlamasından sonra düşür |
| A1.8 | Sual mətnində formul: `$x^2+1$` | Səhifədə düzgün render olunur (MathText) |

### A2. Excel importu

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| A2.1 | İmtahan → "Excel-dən import" → şablonu endir | `.xlsx` şablon enir, sütunlar: sual, variantlar, düzgün cavab, mövzu, çətinlik |
| A2.2 | Şablonu 3–4 sualla doldur → "Önizləmə" | Cədvəl görünür, xəta yoxdursa "Import et" aktivdir |
| A2.3 | Bir sətirdə düzgün cavabı boş burax → önizləmə | Həmin sətir xəta ilə işarələnir, import bloklanır (heç nə yazılmır) |
| A2.4 | Düzgün faylı import et | Suallar bankda və imtahanda görünür, dilləri imtahanın sektoruna bərabərdir |
| A2.5 | Eyni faylı ikinci dəfə import et | Suallar təkrarlanır (bu normaldır) — təkrarları əl ilə silmək olar |

### A3. Bankdan imtahan generasiyası

Şərt: bankda kifayət qədər sual olmalıdır. Mövzu sınağı üçün mövzulara **rüb** verilməlidir
(Admin → Mövzular).

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| A3.1 | Admin → İmtahanlar → "Bankdan imtahan yarat" | Forma: kateqoriya, **tədris sektoru**, rüb, variant sayı, fənn üzrə sual sayı |
| A3.2 | Kateqoriya seç | Fənn siyahısı yalnız həmin kateqoriyanın (və sektorun) fənləridir |
| A3.3 | Sektoru "Rus" et | Fənn siyahısı dəyişir (I mərhələdə ana dili "Rus dili" olur) |
| A3.4 | Bankda olmayan sayda sual istə (məs. 500) | "Bankda kifayət qədər sual yoxdur" — hansı fəndə neçə çatmadığı yazılır, **heç bir imtahan yaranmır** |
| A3.5 | 3 variant × 5 sual istə (bankda ≥15 sual) | 3 qaralama imtahan yaranır: A, B, C. Suallar variantlar arasında **təkrarlanmır** |
| A3.6 | Rüb seç + "kumulyativ" işarələ | Suallar 1-ci rübdən seçilmiş rübə qədərki mövzulardan gəlir |
| A3.7 | İki xarici dil seç | "Bir imtahana yalnız bir xarici dil düşə bilər" xətası |
| A3.8 | Yaranan imtahana bax | Qaralamadır (`dərc olunmayıb`, `deaktiv`), bölmələr fənlərə görə ayrılıb |

### A4. "Əvəz et" (sualı bankdan başqası ilə dəyişmək)

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| A4.1 | Qaralama imtahanda sualın yanında "Əvəz et" | Sual eyni fənn/rüb hovuzundan **təsadüfi başqa** sualla dəyişir, yeri (sıra) qorunur |
| A4.2 | İmtahanı dərc et, sonra "Əvəz et" | "Dərc olunmuş imtahanın sualları dəyişdirilmir" xətası |
| A4.3 | Hovuzda əvəz üçün sual qalmayıb | Aydın mesaj, sual dəyişmir |

### A5. Sual bankı və dil uyğunluğu

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| A5.1 | Admin → Sual bankı | "Dil" sütunu (Az/Ru) və dil filtri var |
| A5.2 | Rus sektoru imtahanı aç → "Bankdan sual əlavə et" | Siyahı yalnız **rus dilində** sualları göstərir, yuxarıda xəbərdarlıq var |
| A5.3 | Cəhddə işlənmiş sualı silməyə çalış | "silinmir" — sual bankda qalır (köhnə nəticələr pozulmasın) |
| A5.4 | Cəhddə işlənmiş sualın düzgün cavabını dəyiş | Backend bloklayır (yalnız xəbərdarlıq deyil), xəta mesajı görünür |
| A5.5 | Eyni sualın mətnini/izahını düzəlt | İcazə verilir (bal dəyişmir) |

### A6. Dərc və imtahan parametrləri

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| A6.1 | İmtahan yarat: sektor seç, pulsuz işarələ | Yaranır; avtomatik bir bölmə açılır |
| A6.2 | Ödənişli seç, qiyməti 0 qoy | "Ödənişli imtahanın qiyməti sıfırdan böyük olmalıdır" xətası |
| A6.3 | Sualı olan imtahanın sektorunu dəyiş | "Sual bağlanmış imtahanın tədris sektoru dəyişdirilmir" xətası |
| A6.4 | İmtahanı dərc et (aktiv + dərc) | Kataloqda və şagird kabinetində görünür |
| A6.5 | Dərcdən çıxar | Kataloqdan yox olur, köhnə nəticələr qalır |

### A7. Giriş hüququnun əl ilə verilməsi

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| A7.1 | İmtahan → "Giriş" → şagird seç, qeyd yaz, cəhd sayı = 1 | Sətir əlavə olunur: mənbə "admin icazəsi", verən admin görünür |
| A7.2 | Şagird hesabı ilə həmin imtahanı aç | "Başla" düyməsi aktivdir |
| A7.3 | Şagird bir cəhd edib bitirir, ikinci dəfə başlamağa çalışır | İcazə verilmir (cəhd limiti) |
| A7.4 | Admin girişi ləğv edir | Şagirddə imtahan bağlanır, **köhnə nəticə səhifəsi açıq qalır** |

---

## B. Şagird axını

### B1. Qeydiyyat və sektor

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| B1.1 | `/register` aç | "Tədris dili" seçimi var, **Azərbaycan sektoru** öncədən seçilib |
| B1.2 | `/ru/register` aç | Eyni forma rusca, **Рус sektoru** öncədən seçilib |
| B1.3 | Telefonu `055 123 45 67` kimi yaz | Qeydiyyat keçir, bazada `+994551234567` formatında saxlanılır |
| B1.4 | Şərtlərlə razılaşmadan göndər | "Qeydiyyat üçün şərtlər və qaydalarla razılaşmalısan" xətası |
| B1.5 | Qeydiyyatdan sonra profilə keç | "Tədris dili" sahəsi var, dəyişdirilə bilir |
| B1.6 | Sektoru "Rus" et və yadda saxla | Kabinetdə yalnız rus sektoru imtahanları görünür (hazırda 0 — normaldır) |

### B2. Kataloq (qonaq)

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| B2.1 | `/abituriyent` | Səhifə açılır, alt bölmələr və fənlər görünür |
| B2.2 | `/ru/abituriyent` | **301** ilə `/ru/abiturient`-ə yönləndirilir |
| B2.3 | `/ru/abiturient` | Rusca səhifə; keçidlər `/ru/abiturient/...` şəklindədir |
| B2.4 | Səhifədəki "Tədris dili" keçidi (Azərbaycan/Rus) | Seçim dəyişir, imtahan siyahısı həmin sektora görə süzülür, seçim sonrakı səhifələrdə qalır |
| B2.5 | Daxil olmuş şagirdlə kataloqa bax | Sektor keçidi **görünmür** (profildən idarə olunur) |
| B2.6 | `/miq`, `/mekteb`, `/suruculuk-imtahani` | Köhnə ünvanlar işləyir (dəyişməyib) |
| B2.7 | Səhifə mənbəyində `<link rel="canonical">` və `hreflang` | Var; ru səhifədə canonical `/ru/abiturient` |
| B2.8 | `/sitemap.xml` | XML açılır, hər səhifə iki dildə, `x-default` var |
| B2.9 | `/robots.txt` | **`User-agent: *` + `Disallow: /`** — sayt hazırda axtarış sistemlərinə bağlıdır (`SEO_INDEXING=false`), sitemap göstərilmir |
| B2.10 | İstənilən səhifənin mənbəyi / başlıqları | `<meta name="robots" content="noindex, nofollow">` və `X-Robots-Tag: noindex, nofollow` var. **Əsl domenə keçəndə `SEO_INDEXING=true` ilə hər ikisi yox olmalıdır** |

### B3. Mövzu sınağı axını

Şərt: A3-də rüblü mövzu sınağı imtahanı yaradılıb və dərc olunub.

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| B3.1 | Kateqoriya səhifəsində "Mövzu sınağı" bloku | Yalnız dərc olunmuş rüb imtahanı olanda görünür |
| B3.2 | Blokdakı keçid → `/…/movzu-sinagi` | Rüb seçimi səhifəsi: hansı rübdə neçə imtahan var |
| B3.3 | Rübə keç → `/…/movzu-sinagi/2-ci-rub` | Yalnız həmin rübün imtahanları; canonical bu səhifəyə işarə edir |
| B3.4 | `/…/2-ci-rub` (mövzu-sınağı seqmenti olmadan) | 404 |

### B4. İmtahan verilməsi

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| B4.1 | Pulsuz imtahanı başlat | Taymer işləyir, suallar bölmə-bölmə görünür |
| B4.2 | Test sualında variant seç | Cavab dərhal saxlanılır (səhifəni yeniləyəndə itmir) |
| B4.3 | Qısa cavablı sualda `0,5` yaz, 1 saniyə gözlə | Cavab avtomatik saxlanılır (~0.8 san gecikmə ilə) |
| B4.4 | Açıq (yazılı) sualda mətn yaz | Saxlanılır; nəticədə "yoxlama gözləyir" statusu olur |
| B4.5 | Bir sualı boş burax | Nəticədə "boş" sayılır, **mənfi bal gətirmir** |
| B4.6 | "Bitir" düyməsi | Təsdiq soruşulur, sonra nəticə səhifəsi açılır |
| B4.7 | Vaxt bitənə qədər gözlə (qısa müddətli imtahanda) | Cəhd avtomatik bağlanır, nəticə hesablanır |
| B4.8 | Bitmiş cəhdə yenidən cavab göndərməyə çalış | Qəbul olunmur |

### B5. Nəticə səhifəsi

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| B5.1 | Ümumi bal | `225.00 / 400 (56.3%)` formatında — "NB" adlandırılmır |
| B5.2 | Fənn cədvəli | Hər fənn üçün düz/səhv/boş, **NB (100-lük)** və çəkili fənn balı (məs. 150-dən) |
| B5.3 | Səhv cavablar | Düzgün cavab və izah göstərilir |
| B5.4 | Açıq sual | "Yoxlama gözləyir"; admin qiymətləndirəndən sonra bal artır |
| B5.5 | Eyni imtahanı ikinci dəfə ver | Nəticədə "Əvvəlki cəhd: … , dəyişmə: +/−" bloku və qrafik görünür |
| B5.6 | "Mövzu üzrə bölgü" | Ən zəif mövzu yuxarıda, düzgünlük faizi ilə |
| B5.7 | Admin sualı imtahandan ayırır və ya əvəz edir | **Köhnə nəticə səhifəsi dəyişmir** (cəhd dondurulub) |

### B6. Statistika

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| B6.1 | Menyu → "Statistika" | Ümumi göstəricilər: cəhd sayı, orta/ən yüksək nisbi bal, keçən vaxt |
| B6.2 | Cəhd qrafiki | Son cəhdlərin nisbi balı xətt qrafikində |
| B6.3 | Fənn cədvəli | Orta, ən yüksək, son nəticə və son iki cəhdin fərqi (yaşıl/qırmızı) |
| B6.4 | Zəif mövzular | Yalnız ≥3 sual cavablandırılmış və <60% mövzular |
| B6.5 | "Giriş hüququm olan imtahanlar" | Pulsuz/admin icazəsi/ödəniş mənbəyi ilə siyahı |
| B6.6 | Yeni hesabla statistikaya bax | Boş vəziyyət mesajları görünür, səhifə sınmır |

---

## C. Ödəniş axını (fake)

> **Diqqət:** produksiyada `APP_ENV=production` və `PAYMENT_DRIVER=fake` olduğuna görə
> sınaq ödənişi **qəsdən bağlıdır**: "Al" düyməsi göstərilmir, alış cəhdi xəta verir.
> Bu, təhlükəsizlik tələbidir (saxta "Uğurlu" düyməsi ilə pulsuz giriş alınmasın).
>
> Ona görə bu bölmə **staging qurulandan sonra** (P2.5) yoxlanılır. Bu gün yalnız C1-i yoxla.

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| C1 | Produksiyada ödənişli imtahan yarat və şagird kimi aç | "Al" düyməsi **görünmür** (alış bağlıdır), pulsuz imtahanlar normal işləyir |
| C2 | *(staging)* "Al" → sınaq bank səhifəsi | Məbləğ və imtahan adı düzgün göstərilir |
| C3 | *(staging)* "Uğurlu" | Giriş açılır, ödəniş `paid` olur, `exam_accesses` sətri yaranır |
| C4 | *(staging)* "Uğursuz" | Giriş açılmır, ödəniş `failed` olur |
| C5 | *(staging)* Eyni callback-i iki dəfə göndər | İkinci dəfə heç nə dəyişmir (idempotent), ikiqat giriş yaranmır |
| C6 | *(staging)* Alınmış imtahanı yenidən al | Yeni sətir yaranmır, mövcud giriş yenilənir |
| C7 | *(staging)* Admin ödənişi geri qaytarır | Giriş ləğv olunur, köhnə nəticə qalır |

---

## D. Tapılan problemləri necə yazmaq

Hər problem üçün: **hansı bənd** (məs. A3.5), **nə etdim**, **nə gözləyirdim**, **nə oldu**,
mümkünsə ekran şəkli və URL. Səhifə xətası (500) görsən, `storage/logs/laravel.log`-un son
sətirlərini də əlavə et.
