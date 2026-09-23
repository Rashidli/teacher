# Əl ilə yoxlama siyahısı

Bu siyahı canlı saytda (https://teacher.cvhazirla.az) əl ilə keçirilən yoxlama üçündür.
Hər bəndin yanında **gözlənilən nəticə** yazılıb — fərqli nəticə görsən, qeyd et.

Avtomatik testlər bu axınların çoxunu onsuz da yoxlayır (`php artisan test` — 472 test),
buradakı məqsəd interfeysin real brauzerdə davranışıdır.

**Yoxlamadan əvvəl:** `php artisan db:backup` (test datası yaradacaqsan).

---

## Sual tipləri: imtahan növünə görə

`config/questions.php` hər kateqoriya üçün icazəli sual tiplərini saxlayır:

| Kateqoriya | İcazəli tiplər |
|---|---|
| Sürücülük | yalnız qapalı (şəkilli) |
| MİQ, sertifikasiya, diaqnostik, məktəbəqədər | yalnız qapalı |
| Dövlət qulluğu — BB və AC | yalnız qapalı |
| Dövlət qulluğu — BA, AB, AA | qapalı + yazılı açıq |
| Magistratura | qapalı + kodlaşdırılan + esse |
| Buraxılış, I və II mərhələ | qapalı + kodlaşdırılan + yazılı |

Qayda üç yerdə tətbiq olunur: **admin sual forması** (yalnız icazəli növ seçilir),
**bankdan generasiya** (icazəsiz tip hovuza düşmür) və **nümunə məzmun seeder-i**.
Uyğunluq ən uzun prefiksə görədir, yəni alt düyün valideyndən dəqiq qayda təyin edə bilər.

## Demo məzmun

Kataloqun hər düyünündə imtahan, hər filtrdə variant olsun deyə nümunə məzmun ayrıca
seeder ilə qurulur. Real data ilə qarışmır: bütün qeydlər `is_demo = 1` ilə işarələnir.

**Şagird tərəfdə "demo" sözü görünmür** — imtahan adları, izahları və sual mətnləri təmizdir,
nümunə məzmun real məzmundan seçilmir. Ayırd etmək üçün **admin paneldə** (imtahan siyahısı və
sual bankı) kiçik sarı **`demo`** nişanı var. Slug-larda `demo-` hissəsi qalır ki, mövcud
ünvanlar qırılmasın.

```bash
php artisan db:seed --class=DemoContentSeeder --force   # qurur (idempotent)
php artisan demo:clear --dry-run                        # nə silinəcəyini göstərir
php artisan demo:clear --force                          # silir
```

- Seeder produksiyada xəbərdarlıq verir və `--force` (və ya interaktiv təsdiq) olmadan
  heç nə yazmır. `DatabaseSeeder`-ə qoşulmayıb: adi `db:seed` onu çağırmır.
- Təkrar işlədilə bilər — mövzu `slug`, sual `source`, imtahan `slug` açarı ilə tapılır,
  data ikiləşmir.
- Hər sektor üçün bir demo şagird yaradılır (`demo.az@example.test`, `demo.ru@example.test`).
  **Parol hər işə salmada yenidən təsadüfi qurulur və yalnız seeder çıxışında göstərilir** —
  kodda saxlanılmır. Bu hesablarda tamamlanmış cəhdlər var, ona görə nəticə, statistika və
  admin qiymətləndirmə ekranları boş qalmır.
- `demo:clear` real dataya toxunmur: demo sual real imtahanda işlənibsə, demo mövzuya real
  sual bağlıdırsa — saxlanılır və hesabatda göstərilir.
- Seeder idempotent olduğu üçün adların dəyişməsi də onun təkrar işə salınması ilə tətbiq
  olunur (mövcud sətirlər yenilənir, slug-lar dəyişmir).
- **Real istifadəçi gəlməzdən əvvəl `demo:clear` işlədilməlidir** (demo imtahanlar dərc
  olunmuş olduğu üçün kataloqda hamıya görünür).

---

## Giriş və rollar (əvvəlcə bunu yoxla)

Sayt **tək sessiya (web guard) + rollar** üzərində işləyir: admin, müəllim və şagird eyni
`users` cədvəlindədir, ayrı giriş sistemi yoxdur. Bir hesabın **bir neçə rolu** ola bilər.

- Vahid `/login` heç bir rolu rədd etmir — girişdən sonra hesab öz panelinə düşür:
  admin → `/admin/dashboard`, müəllim (modul açıq olanda) → müəllim paneli, qalanlar →
  `/student/dashboard`. Heç bir paneli olmayan hesab izahlı `/panel-yoxdur` səhifəsini görür.
- `/admin/login` **yeganə** ayrıca giriş səhifəsidir: yalnız admin rolunu buraxır və sürət
  limiti var. Uğursuz girişin səbəbi (səhv parol, yoxsa rol çatışmazlığı) **açıqlanmır**.
- Ayrıca müəllim girişi **yoxdur**: modul açılanda `/teacher/login` 301 ilə `/login`-ə aparır,
  müəllim də vahid formadan daxil olur.
- Başqa rolun panelinə girmək cəhdi **403** verir (giriş səhifəsinə yönləndirmə yox).

Hesablar: bir admin, bir şagird, **çox rollu bir hesab** və L19 üçün **yalnız müəllim rolu olan**
bir hesab lazımdır. `rashidliseymur@gmail.com` — `teacher` + `student` rolları var.

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| L1 | Şagird hesabı ilə `/login` | `/student/dashboard` açılır, menyuda yalnız şagird bölmələri |
| L2 | Admin hesabı ilə **eyni** `/login` | `/admin/dashboard` açılır — "bu hesab şagird deyil" kimi xəta **yoxdur** |
| L3 | Çox rollu hesabla (`rashidliseymur@gmail.com`) `/login` | Müəllim modulu söndürülü olduğuna görə şagird kabinetinə düşür; menyuda müəllim bölməsi görünmür, şagird rolu işləyir |
| L4 | Çox rollu hesabla `/admin/dashboard` aç | **403** (bu hesabın admin rolu yoxdur) |
| L5 | Şagird hesabı ilə `/admin/dashboard` aç | **403** |
| L6 | Yalnız admin rolu olan hesabla `/student/dashboard` aç | **403** |
| L7 | `/admin/login`-ə şagird hesabının email və **düzgün** parolu ilə gir | "Daxil edilən məlumatlar yanlışdır" xətası; geri qayıdanda **hələ də qonaqsan** (sessiya açılmır) |
| L8 | Eyni hesabla, bu dəfə **səhv** parolla | **Tam eyni mesaj** — cavabdan parolun düz olub-olmadığı bilinmir |
| L9 | Şagird kimi daxil olub `/admin/login` aç | Giriş forması görünür (şagird kabinetinə atılmır) |
| L10 | Həmin formadan admin hesabının məlumatları ilə gir | Admin panelinə keçir, sessiya admin hesabına dəyişir |
| L11 | Admin kimi daxil olub `/admin/login` aç | `/admin/dashboard`-a yönləndirilir, forma göstərilmir |
| L12 | `/admin/login`-də səhv parolla **5 dəfə** cəhd et, sonra **düzgün** parol yaz | "… saniyə ərzində yenidən cəhd edin" mesajı, düzgün parolla da giriş açılmır. Limit bitəndən sonra normal işləyir |
| L13 | Qonaq kimi `/admin/dashboard` və `/student/dashboard` aç | Uyğun olaraq `/admin/login` və `/login`-ə yönləndirilir (403 yox) |
| L14 | Daxil olduqdan sonra `/login` aç | Öz panelinə qaytarılır, giriş forması göstərilmir |
| L15 | Köhnə ünvanlar: `/student/login`, `/student/register` | **301** ilə `/login` və `/register`-ə yönləndirilir |
| L16 | `/teacher/login`, `/teacher/register` | **404** — müəllim modulu söndürülüb (`FEATURE_TEACHERS=false`). Modul açılanda `/teacher/login` **301** ilə `/login`-ə yönləndirilir, ayrıca müəllim giriş forması yoxdur |
| L17 | İstənilən paneldən "Çıxış" | Sessiya bağlanır; geri düyməsi ilə panelə qayıtmaq olmur |
| L18 | `/register`-dən yeni hesab aç | Hesab **həmişə şagird** olur: qeydiyyatdan sonra şagird kabineti açılır, admin/müəllim bölmələri görünmür |
| L19 | **Yalnız `teacher` rolu olan** hesabla `/login` (modul söndürülü) | 403 **yox**: `/panel-yoxdur` səhifəsi açılır — müəllim modulunun bağlı olduğu izah olunur, hesabın rolları və "Çıxış" düyməsi görünür |
| L20 | Şagird hesabı ilə `/panel-yoxdur` aç | Öz kabinetinə qaytarılır (səhifə yalnız paneli olmayanlar üçündür) |

---

## K. Kataloq və imtahan səhifəsi

Kataloqun iki girişi var: **kateqoriya ağacı** (ana səhifədən kök bölməyə, oradan alt
bölmələrə) və **`/imtahanlar`** — ağacdan asılı olmayan ümumi siyahı, ən yeni imtahan
əvvəldə. Hər imtahanın ictimai səhifəsi var: `/imtahan/{slug}` — qonaq da görür.

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| K1 | Qonaq kimi `/mekteb/9-cu-sinif-buraxilis` aç | Düyünün **və alt düyünlərinin** imtahanları kart kimi görünür |
| K2 | İmtahan kartına kliklə | `/imtahan/{slug}` açılır — **giriş formasına atmır** |
| K3 | İmtahan səhifəsinə bax | Kateqoriya zənciri, müddət, sual sayı, maksimal bal, növ (+rüb), qiymət və bölmələr üzrə fənn siyahısı görünür |
| K4 | Kataloqda filtrləri işlət (növ, rüb, fənn, qiymət) | Siyahı süzülür, **ünvanda query görünür** (`?nov=…&fenn=…`); səhifəni yeniləyəndə seçim qalır, link paylaşıla bilir |
| K5 | Çoxfənli imtahanı ikinci fənninə görə süz | İmtahan tapılır (filtr bölmələrə baxır, imtahanın əsas fənninə yox) |
| K6 | Qonaq kimi pulsuz imtahanda "Başla" bas | Giriş səhifəsi açılır; girişdən sonra **həmin imtahan səhifəsinə** qayıdırsan |
| K7 | Qayıtdıqdan sonra səhifəyə bax | **Cəhd başlamayıb**, taymer işləmir; "Başla" düyməsi durur. Yalnız onu basanda cəhd yaranır |
| K8 | Ödənişli imtahanda daxil olmuş şagird kimi bax | "Al" düyməsi (produksiyada alış bağlıdırsa düymə yox, izah var) |
| K9 | Yarımçıq cəhdi olan imtahanı aç | "Davam et" düyməsi və qalan vaxt görünür |
| K10 | Rus sektoru seç, sonra az imtahanının ünvanını aç | **404** (sektor qaydası ictimai səhifədə də işləyir) |
| K11 | Menyu → "Mənim imtahanlarım" | Davam edən, girişi olan və tamamlanmış imtahanlar; **filtr yoxdur** |
| K12 | Heç bir imtahanı olmayan hesabla həmin səhifə | "Hələ imtahanın yoxdur" + **"Kataloqa keç"** düyməsi ana səhifəyə aparır |
| K13 | Köhnə ünvan `/student/exams/{id}` | **301** ilə `/imtahan/{slug}`-a yönləndirilir |
| K14 | `/sitemap.xml` | Hər dərc olunmuş imtahan **bir dəfə** — az sektoru `/imtahan/{slug}`, rus sektoru `/ru/imtahan/{slug}` (qaralamalar yoxdur) |
| K15 | İmtahan səhifəsinin mənbəyi | `canonical` sektorun əsas ünvanına göstərir, **`hreflang` yoxdur** (imtahan mətni tərcümə olunmur); `SEO_INDEXING=false` olduğu üçün `noindex` var |
| K16 | Az imtahanını `/ru/imtahan/{slug}` ilə aç | Səhifə açılır (interfeys rusca), amma `canonical` yenə `/imtahan/{slug}`-dır |
| K30 | Başlıqda "İmtahanlar" linkini bas | `/imtahanlar` açılır, imtahanlar ən yenisindən sıralanır |
| K31 | Ekranı 400px-dən dar et | Başlıqdakı linklər **hamburger menyusuna** yığılır; menyuda İmtahanlar, Giriş, Qeydiyyat var, Escape onu bağlayır |
| K32 | `/imtahanlar`-da mobildə "Filtrləri göstər" | Panel açılır; seçim edəndən sonra düymədə **aktiv filtr sayı** görünür |
| K33 | Kateqoriya, növ, fənn, qiymət seçimlərini birləşdir | Siyahı daralır, **seçim URL-də qalır** (səhifəni yeniləyəndə itmir), hər variantın yanında sayğac var |
| K34 | Axtarış sahəsinə imtahan adından bir hissə yaz | Siyahı süzülür; 1 hərf yazanda filtr tətbiq olunmur |
| K35 | Səhifə 2-yə keç, sonra filtr dəyiş | Filtr dəyişəndə **birinci səhifəyə** qayıdılır |
| K36 | Filtrli ünvanın səhifə mənbəyində `<link rel="canonical">` | Həmişə **filtrsiz** `/imtahanlar`-a (ru-da `/ru/imtahanlar`) göstərir |
| K37 | Sektoru "Rus"a keçir | Yalnız ru sektorunun imtahanları qalır; sayğaclar da dəyişir |
| K38 | Telefonda (360px) `/imtahanlar`, kateqoriya və imtahan səhifəsi | **Üfüqi sürüşmə olmamalıdır**; kartlar tək sütun, çiplər və düymələr barmaqla rahat basılır (44px) |
| K39 | `/imtahanlar`-ı filtrsiz aç | **Bölmələr üzrə qruplar**: hər kök kateqoriya üçün başlıq, 4 kart və "Hamısına bax (N)" keçidi — bir bölmə səhifəni tutmur |
| K40 | Yalnız sıralamanı dəyiş (məs. "Ucuzdan bahaya") | Qruplar **qalır**, hər bölmənin içi seçilmiş sıraya görə düzülür |
| K41 | İstənilən filtri və ya axtarışı seç | Düz siyahıya keçir, səhifələmə görünür |
| K42 | Filtr panelində kateqoriya sətrindəki **+** düyməsi | Alt bölmələr açılır (akkordeon); fənn siyahısında 6-dan sonra "Daha çox (N)" |
| K43 | Bir neçə filtr seç, sonra çiplərdən birinin **×**-ini bas | Yalnız o filtr silinir, digərləri qalır; "Hamısını sıfırla" hamısını təmizləyir |
| K44 | Filtr panelinin ən yuxarısı | **"İmtahanın dili (sektor)"** seçimi orada olur və izahı var — başlıqdakı AZ|RU ilə qarışmır |
| K45 | Karta bax | Üst sətir bölmə yolu (rəngli nöqtə ilə), başlıq yalnız növ (+rüb), altında fənlər/sual/müddət, aşağıda qiymət və ya yaşıl "Pulsuz" nişanı. **Kartın hər yeri klikləniəndir**, Tab ilə fokus başlığa düşür |
| K46 | İmtahan səhifəsi | Bölmə rəngli üst sətir, növ etiketi, müddət/sual/bal bir sətirdə ikonlarla, qiymət və əsas düymə vurğulu blokda |
| K47 | Daxil olmamış halda başlıq | "Daxil ol" və "Qeydiyyat" görünür |
| K48 | Daxil olduqdan sonra başlıq | İnisiallı düymə + ad; menyuda Mənim imtahanlarım, Nəticələr, Statistika, Profil, Çıxış (admin hesabında əlavə "Admin panel"). Escape və kənara klik menyunu bağlayır |
| K49 | Pullu imtahanda "Al" bas | Bank səhifəsi açılmır: ödəniş dərhal təsdiqlənir, "Test rejimi" bildirişi görünür, imtahan açılır və "Başla" işləyir |
| K50 | `/imtahanlar`-da masaüstündə siyahını aşağı sürüşdür | Filtr paneli **ekranda qalır**; panel uzundursa öz daxilində sürüşür, səhifə ilə birlikdə yuxarı getmir |
| K51 | Sürücülük imtahanını başlat | Suallarda **yol nişanı şəkilləri** görünür; mobildə şəkil ekrana sığır, üfüqi sürüşmə yaratmır |
| K52 | Şəkilli sualı cavablandırıb nəticəyə bax | Nəticə səhifəsində də şəkil görünür |
| K53 | Admin → sürücülük və ya MİQ imtahanında "Sual əlavə et" | Yalnız **Test** növü seçilə bilir, izahı yazılıb. Abituriyent imtahanında hər üç növ var |
| K54 | Admin → şəkil yüklə | Şəklin altında **"Şəklin təsviri (alt mətni)"** sahəsi çıxır |

---

## A. Admin paneli

Giriş: `/admin/login` (və ya vahid `/login` — admin hesabı hər ikisindən eyni panelə düşür).
Rol və limit yoxlamaları yuxarıdakı "Giriş və rollar" bölməsindədir.

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

### B5.5 Şagird paneli

| # | Addım | Gözlənilən nəticə |
|---|---|---|
| B5.5.1 | Kabinet → "Panel" | Dörd göstərici, **davam edən imtahanlar**, **son nəticələr** və başlıqda "Kataloqa keç" düyməsi |
| B5.5.2 | Paneldə imtahan tövsiyəsi axtar | **"Mövcud İmtahanlar" bloku yoxdur** — yeni imtahan kataloqdan tapılır (o yer P3-dəki "Məqsədim" üçün saxlanılıb) |
| B5.5.3 | Son nəticə sətrinə kliklə | Həmin cəhdin nəticə səhifəsi açılır |

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

## Ödəniş: TEST REJİMİ

`PAYMENT_DRIVER=fake` olduğu üçün **"Al" basılanda ödəniş dərhal təsdiqlənir**, giriş açılır
və şagird imtahana başlaya bilir. Real pul hərəkət etmir; ödəniş qeydində `provider = fake`
və `payload.test_mode = true` qalır. İmtahan səhifəsində "Test rejimi — real ödəniş getmir"
xəbərdarlığı görünür.

> **⚠️ Öz domenimizə keçməzdən ƏVVƏL `.env`-də `PAYMENT_DRIVER` real provayderə
> dəyişdirilməlidir.** Əks halda bütün ödənişli imtahanlar faktiki olaraq pulsuz olar.
> Bu, tək açardır — başqa şərt yoxdur (bax ROADMAP P2.5).

Uğursuz ödəniş axınını əl ilə yoxlamaq üçün sınaq bank səhifəsi (`/payments/fake/{payment}`)
saxlanılıb; adi "Al" axınında işlədilmir.

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
