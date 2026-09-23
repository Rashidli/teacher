# Proyekt Dəyişiklikləri

## Ümumi Məlumat

**Sayt:** https://teacher.cvhazirla.az  
**Framework:** Laravel + Inertia.js + Vue.js  
**DB:** MySQL (`websites_teacher_exam_db`)

---

## Jurnal (yeni dəyişikliklər üstdə)

### 2026-09-23 — DİM-in açıq tapşırıq növləri (kodlaşdırılan və yazılı)

DİM-də açıq tapşırıqlar iki qrupdur: **kodlaşdırılan** (variantlar verilir, cavab
kodlaşdırılır və avtomatik yoxlanılır) və **yazılı** (variantsız, meyarla qiymətləndirilir).
İndi hər ikisinin alt növləri var — `questions.subtype`.

**Kodlaşdırılan (`open_coded`)** — hamısı avtomatik yoxlanılır, xam dəyəri qapalı sual kimi
**1 bal**:

| Alt növ | Cavabın kodu | Düzgün cavab haradan gəlir |
|---|---|---|
| `numeric` (hesablama) | "0,5" | `accepted_answers` (köhnə davranış) |
| `multi_select` (seçim) | "A,C" | `is_correct` variantlar |
| `ordering` (ardıcıllıq) | "C,A,B" | variantların `order` sırası |
| `matching` (uyğunluq) | "1-2,2-1" | `pairs` cütlərinin sırası |

Yoxlama `App\Support\CodedAnswer`-dədir. **Düzgün cavab saxlanılmır, hesablanır** — admin
variantı düzəldəndə "düzgün cavab" köhnəlmir. Kod HƏRFLƏ işləyir, id ilə yox: sual
kopyalananda variantların id-si dəyişir, hərflər qalır.

**Yazılı (`open_written`)** alt növləri — `serbest`, `situasiya`, `metn`, `menbe`, `isbat`
(DİM-in 2027 modelinə görə). **Bal qaydasını dəyişmir**, yalnız məlumat və filtr üçündür.
Mətn və mənbə əsaslı tapşırıqlarda mətn ayrıca `passages` cədvəlindədir: **bir mətnə bir
neçə sual bağlana bilər**.

- **Admin formasında** alt növ seçicisi və hər alt növ üçün öz redaktoru: seçimdə çoxlu
  işarələmə, ardıcıllıqda ↑↓ ilə düzmə (sıra düzgün cavabdır), uyğunluqda sol-sağ cütlər.
  Cəhdlərdə işlənmiş sualda alt növ, düzgün ardıcıllıq və cütlər **bloklanır**.
- **İmport**: "tip" sütununda yeni adlar — `secim`, `ardicilliq`, `uygunluq` (+ `hesablama`);
  yazılının alt növü isə `alt_tip` sütunundadır. Uyğunluq cütləri `Bakı=Azərbaycan|…`
  formasındadır. Köhnə fayllar dəyişmir: `qisa` hələ də hesablamadır. Şablon və izah vərəqi
  yeniləndi.
- **İmtahan səhifəsində** hər növ üçün uyğun interfeys: checkbox, sıralama düymələri,
  uyğunluq seçimi. Bəndlər cəhdə görə SABİT qarışdırılır (səhifə yeniləndikdə yerini
  dəyişmir), düzgün sıra isə interfeysdən oxunmur. Mətn/mənbə sualın üstündə açılan blokdadır.
- **Nəticə səhifəsində** cavab kartı kodu göstərir ("A, C" / "1 → 2").
- **Demo datada** hər alt növdən nümunə var (50 seçim, 50 ardıcıllıq, 41 uyğunluq, 26 mətn,
  26 mənbə). Çərçivə seçimi tipə görə şablonla işləyir: mövzu daxilində tip balansı sabit
  qalır (yalnız qapalı sual qəbul edən imtahanlarda hovuz çatsın), çərçivələr isə
  mövzudan-mövzuya növbə ilə dəyişir. `demo:clear` mətnləri də silir.

---

### 2026-09-23 — İmtahan səhifəsinin layout-u, ilkin bal və kartda imtahanın adı

**İmtahan səhifəsində boşluq.** Tək bölməli imtahanda ("Magistratura tam sınaq") əsas
məlumatla "Bölmələr" arasında böyük boş sahə qalırdı: sağ sütun (qiymət və düymə) soldan
uzun idi, "Bölmələr" isə bütün enin altında başlayırdı. İndi bölmələr sol sütunun
davamıdır (`.panel-main`) — boşluq qalmır, çoxbölməli imtahanda da düzgün axır.

**Nəticədə ilkin bal.** Kiçik "ilkin" nişanı yekun bal kimi oxunurdu. İndi nişan "ilkin bal"
yazır, altında isə konkret rəqəm var: *"Bu ilkin baldır: yoxlanılan 2 sual üçün əlavə
20 bala qədər gələ bilər. Cavab kartında həmin suallar ? ilə işarələnib."* Rəqəm balın öz
düsturu ilə hesablanır (`StudentExamController::pendingPotential()`): bölmədə bir xam bal
vahidi `max_score / rawMax` qədər fənn balı verir, gözləyən sualların çəkisi həmin əmsalla
vurulur. Cavab kartında yoxlanılan suallar əvvəlki kimi `?` ilə qalır.

**Kartda imtahanın öz adı.** Kataloq kartında başlıq imtahanın adıdır, altında kiçik etiket
kimi növ və rüb ("Mövzu sınağı — 2-ci rüb"). Ad avtomatik qurulubsa və kateqoriya + növdən
başqa heç nə demirsə server `title`-ı null göndərir (`App\Support\ExamTitle::isGeneric()`)
və başlıq elə növ etiketi olur — eyni söz iki dəfə yazılmır. Bankdan generasiyada **başlıq
artıq məcburi deyil**: boş qoyulanda `ExamTitle::generate()` "I qrup RK — Mövzu sınağı
(2-ci rüb)" kimi ad qurur, forma isə nümunəni əvvəlcədən göstərir.

**ROADMAP P3-ə əlavə olundu** (işlənmir, yalnız plan): nəticənin ictimai linki və paylaşma
düymələri, imtahanın ulduzla qiymətləndirilməsi; domen alınandan sonra "Hazır ol" səhifəsi,
"Bitir" təsdiq dialoqu və Google ilə qeydiyyat; "bir ekranda bir sual" rejimi.
**Cüzdan/balans sistemi plandan çıxarıldı** — ödəniş birbaşa qalır.

---

### 2026-09-23 — Sinif etiketi ilə kateqoriya arasında iş bölgüsü

Kataloqda "9-cu sinif buraxılış" kateqoriyası ilə "9-cu sinif" etiketi yan-yana düşürdü və
fərqi anlaşılmırdı. **Qayda:** sinif etiketi YALNIZ kateqoriya adı sinfi göstərməyəndə
işlədilir (olimpiada, liseylərə qəbul, mövzu testləri).

- **`App\Support\GradeMention` + `Category::mentionsGrade()`** — adda və ya yolda
  "9-cu sinif", "11 illik", "9 класс" varmı. Yol valideyn zəncirini daşıdığı üçün adı
  neytral olan alt düyün də düzgün tutulur. "1-ci mərhələ", "2-ci qrup", "B kateqoriyası"
  sinif sayılmır.
- **Kataloq:** adı sinif bildirən bölmənin səhifəsində "Sinif" filtri ümumiyyətlə gəlmir
  (`CatalogFilters::options()` kateqoriya kontekstini alır). `/imtahanlar`-da da eyni qayda
  işləyir — həmin kateqoriya çip kimi seçiləndə bölmə yox olur. Filtrin altında izah:
  "Kateqoriyası sinif göstərməyən imtahanlar üçün (olimpiada, liseylərə qəbul, mövzu testləri)".
- **Admin:** imtahan formasında və bankdan generasiyada sinif qrupunun altında qayda yazılıb;
  kateqoriyanın adında sinif varkən sinif etiketi seçilirsə xəbərdarlıq çıxır. **Bloklamır** —
  istisna hallar ola bilər.
- **Demo data:** buraxılış, abituriyent, magistratura, dövlət qulluğu, MİQ və sürücülük
  imtahanlarından sinif etiketləri çıxarıldı (68 → 0). Nümunə üçün adı neytral olan yeganə
  məktəb düyünü ("Orta məktəb") üç məşq testi alır — 4-cü (ibtidai), 6-cı və 8-ci (orta) sinif,
  hər sektorda ayrıca. Beləcə "Sinif" filtri boş qalmır və sınaqdan keçirilə bilir.
  `DemoContentSeeder` indi `TagSeeder`-i özü çağırır: etiketlər olmadan nümunə itirdi.
- **ROADMAP P3:** "Liseylərə qəbul" və "Olimpiadalar" bölmələri qurulanda həmin bölmələrdə
  **kateqoriya adına sinif yazılmamalıdır** — səviyyəni yalnız etiket bildirəcək.

---

### 2026-09-23 — Nəticə səhifəsində təkrar blokların təmizlənməsi

"Bölmələr və irəliləyiş" bloku imtahan vərəqindəki eyni rəqəmləri üçüncü dəfə göstərirdi
(vərəqin xülasəsi, iri "Düzgün / Səhv / Boş" sətri və tək sətirlik bölmə cədvəli).
Blok bölündü və hər parça **yalnız yeni məlumat verəndə** çıxır:

- İri rəqəmli "Düzgün / Səhv / Boş" sətri **tamamilə silindi** — vərəqin xülasəsində var.
- **Fənn üzrə bölgü** cədvəli yalnız çoxfənli imtahanda (`sections.length > 1`). Tək bölmədə
  sətir vərəqdəki yekunun eynisi olurdu.
- **İrəliləyiş**: bir cəhddə blok yoxdur; iki cəhddə bir sətir ("Əvvəlki cəhd: X, dəyişmə: +Y");
  qrafik yalnız üç və daha çox cəhddə.
- **Mövzu üzrə bölgü** ən azı iki mövzu olanda göstərilir — tək mövzuda "zəif yer" anlamı yoxdur.
- Blokların ardıcıllığı: vərəq → (fənn üzrə bölgü) → mövzu üzrə bölgü → sual-cavab analizi → (irəliləyiş).
- Cədvəlin Tailwind sinifləri palitra tokenlərinə keçirildi.

**`LineChart`:** kənar etiketlər mərkəzə görə hizalandığı üçün birinci və sonuncu tarixin
yarısı SVG sərhədindən kənarda qalıb kəsilirdi. İndi kənar nöqtələrdə hizalama içəriyə
çevrilir (`start` / `end`); qrafik konteynerinə də sağdan boşluq verildi. Bu düzəliş
`Student/Statistics` səhifəsindəki qrafiyə də aiddir.

---

### 2026-09-23 — İmtahan etiketləri və kartda "Ətraflı" akkordeonu

**Etiketlər (çox-çoxa).** `tags` + `exam_tag` cədvəlləri. Etiket kateqoriya ağacına
**ortoqonaldır**: kateqoriya imtahanın növünü bildirir (abituriyent II qrup, sürücülük),
etiket isə əlavə əlaməti — ən əsası **sinif səviyyəsini**. Eyni "9-cu sinif" etiketi həm
buraxılış, həm olimpiada imtahanında ola bilər.

- `TagSeeder`: 2-ci … 11-ci sinif. Şəkilçilər ahəng qanununa görə açıq yazılıb
  (2-ci, 3-cü, 4-cü, 5-ci, 6-cı, 7-ci, 8-ci, 9-cu, 10-cu, 11-ci) — avtomatik qurulanda
  "9-ci sinif" kimi səhv çıxırdı. Açar `kind + order` cütüdür, ona görə ad düzəldiləndə
  mövcud sətir yenilənir və imtahanlarla əlaqə qalır.
- **Admin:** `/admin/tags` — siyahı, yeni etiket, aktiv/deaktiv. **İmtahana bağlı etiket
  silinmir**, yalnız deaktiv edilir: əks halda kataloq filtri ilə mövcud imtahanlar
  arasındakı əlaqə səssizcə itərdi. Menyuda "Etiketlər" bəndi.
- **İmtahan formasında və bankdan generasiyada** ortaq `TagPicker` komponenti; generasiyada
  seçilmiş etiketlər bütün variantlara bağlanır.
- **Kataloqda** ayrıca filtr bölməsi: "Sinif" (öz sırası ilə, əlifba ilə yox) və "Etiket".
  Sayğaclar var, seçim URL-də `?etiket=` kimi qalır, silinə bilən çip kimi görünür.
  Kateqoriya səhifəsində də işləyir.
- **Kartda** sinif etiketi kiçik nişan kimi göstərilir.
- Nümunə məzmunda buraxılış imtahanları 9/11, abituriyent 11-ci sinif etiketi alır.

**Kartda "Ətraflı" akkordeonu.** Klikləyəndə kart daxilində bölmələr (fənn və sual sayı),
müddət, digər etiketlər və qısa izah açılır — səhifəni tərk etmək lazım gəlmir. Düymə
`position: relative` + `z-index` ilə başlığın örtən linkindən (stretched link) yuxarıdadır,
ona görə klik imtahan səhifəsini **açmır**. Toxunma sahəsi 44px, mobildə də işləyir.

**ROADMAP P3:** kateqoriya ağacına "Liseylərə qəbul" və "Olimpiadalar" bölmələri (ibtidai və
orta siniflər üçün) — sinif etiketləri artıq hazırdır, qalan ağac düyünləri və sual bankıdır.

**Testlər:** `ExamTagTest` (8) — filtr sayğacları və sinif sırası, filtrin siyahını
daraltması, deaktiv etiketin gizlənməsi, kartın etiket və bölmələri daşıması, kateqoriya
səhifəsindəki filtr, admin əməliyyatları və işlənən etiketin silinməməsi (496 → 504 test).

---

### 2026-09-23 — Şagird panelinin vizual dili və "imtahan vərəqi"

**Panel ictimai tərəflə birləşdi** (nümunənin davamı). Ortaq tokenlər `:root`-da, ortaq
komponentlər `Components/Ui/`-də: `PanelCard`, `PanelButton`, `PanelHead`, `PanelRow`.

- **Şagird paneli, "Mənim imtahanlarım", "Nəticələrim", "Statistika"** eyni kart sisteminə,
  şriftlərə və palitraya keçdi. Siyahı sətirlərində imtahanın **bölmə rəngi və yolu** var
  (kataloq kartındakı ilə eyni), düymələrdə toxunma sahəsi 44px.
- **Nəticələr səhifəsində iki gizli səhv** tapıldı: şablon `attempt.score` və
  `attempt.total_questions` sahələrini oxuyurdu, halbuki `exam_attempts` cədvəlində belə
  sütun yoxdur — "Bal" və "ümumi" sütunları **boş görünürdü**. Payload açıq quruldu; cədvəl
  sətir siyahısına çevrildi (mobildə üfüqi sürüşmə yoxdur), "Qrup" sütunu isə (qrupsuz
  imtahanlarda boş qalırdı) bölmə adı ilə əvəzləndi. Siyahıya yoxlama gözləyən cəhdlər də
  düşür — əvvəl onlar ümumiyyətlə görünmürdü.

**İmtahan vərəqi (yeni).** Nəticə səhifəsinin başında sənəd görünüşü:

- **Başlıq:** brend nişanı və adı, şagirdin adı, imtahan, bölmə, tarix və sərf olunan vaxt.
- **Cavab kartı:** sual nömrələri sətri, altında şagirdin cavabı (qapalıda variant hərfi),
  altında düzgün cavab, altında **sualın dəyəri** (qapalı 1 bal, yazılı 2 bal — tiplərin
  çəkisi fərqlidir). Vəziyyət həm rənglə, həm **işarə ilə** (✓ ✗ — ? ±) verilir: fon tam
  doldurulmur, yumşaq fon + sol kənarda rəngli zolaq işlənir, mətn oxunaqlı qalır.
- **Yekun:** nisbi bal, xam bal, düz/qismən/səhv/cavabsız/yoxlanılır sayları.
- Açıq suallar yoxlanmayıbsa vərəqdə də **"ilkin"** nişanı və izah var.
- Cədvəl mobildə **öz konteynerində** sürüşür, birinci sütun yapışıq qalır.
- **`@media print`:** naviqasiya, səhifə başlığı, flash, düymələr və qrafik gizlənir; fon
  ağ olur, rəngli xanalar `print-color-adjust: exact` ilə saxlanılır, vərəq və bloklar
  səhifə ortasından kəsilmir. "Çap et / PDF" düyməsi əlavə olundu.
- Mövcud **sual-cavab analizi** bölməsi olduğu kimi qalır, vərəq onun üstündə xülasədir.

**Testlər:** `ResultSheetTest` (3) — vərəq başlığındakı şagird adı və müddət, variant
hərflərinin göndərilməsi, sualın xam dəyəri (493 → 496 test).

---

### 2026-09-23 — Paneldən sayta qayıdış və test paketinin fayl riski

**Paneldən kataloqa qayıdış (səhv düzəlişi).** Şagird panelə keçəndən sonra kataloqa qayıda
bilmirdi — yəni yeni imtahan seçə bilmirdi. Panel loqosu `dashboard`-a, "Kataloqa keç"
düymələri isə ANA SƏHİFƏYƏ aparırdı.

- `AuthenticatedLayout.vue`: loqo artıq **ana səhifəyə** aparır; menyuya sabit
  **"İmtahanlar"** linki əlavə olundu (`exams.catalog`) — həm masaüstü, həm mobil menyuda,
  bütün rollar üçün.
- `MyExams.vue` və `Dashboard.vue`-dakı "Kataloqa keç" düymələri `home` yerinə
  **`exams.catalog`**-a gedir. Boş haldakı mətn də kataloqa yönləndirir.
- Linklər `lroute()` ilə qurulur, yəni rus interfeysində `/ru/imtahanlar`-a düşür.

**Test paketinin produksiya fayllarını silməsi riski.**

- **`Tests\TestCase`-də `Storage::fake('public')` defolt oldu** — hər yeni testdə ayrıca
  yazmaq lazım deyil. Testlər produksiya qovluğunda işlədiyi üçün real diskə toxunan bir
  test bütün sayta zərər verə bilirdi.
- **`demo:clear` fayl silməzdən əvvəl ayrıca qoruyucudan keçir:** `testing` mühitində disk
  saxta deyilsə **ümumiyyətlə silmir** (məhz bu hal şəkilləri məhv etmişdi); digər
  mühitlərdə `--force` yoxdursa qovluq və fayl sayı göstərilib təsdiq soruşulur.
- **`php artisan files:backup`** — yeni əmr. `db:backup` yalnız bazanı götürürdü, şəkillər
  isə `storage/app/public`-dədir: baza geri qaytarılsa sual sətirlərindəki yollar qalır,
  fayllar itir. Arxiv `public_html`-dən kənarda, `0600` hüququ ilə, son 3 nüsxə.
  DEPLOY.md-də 2-ci addım kimi `db:backup`-ın yanındadır.
- **ROADMAP P2.5:** testlərin produksiya qovluğunda işləməsi ayrıca risk kimi yazıldı —
  qoruyucular yalnız bilinən halı bağlayır, öz domenimizə keçəndə testlər ayrıca mühitə
  (staging və ya CI) köçürülməlidir.

**Testlər:** `PanelNavigationTest` (4) — kataloq route-u hər iki dildə mövcuddur, panel
səhifələri onu Ziggy ilə cliente ötürür, şagird kataloqu paneldən aça bilir və ictimai
başlıqdakı hesab menyusu üçün lazım olan `auth.user` prop-u yerindədir, yəni iki layout
arasında keçid qırılmır (489 → 493 test).

---

### 2026-09-23 — Sual şəkillərinin 404-ü və alt mətnindəki cavab sızması

**404 (səbəb tapıldı).** `public/storage` linki də, veb-server də düzgün idi — **fayllar
yox idi**. Onları `php artisan test` silmişdi: `DemoContentTest` `demo:clear` əmrini
çağırır, o isə `Storage::disk('public')->deleteDirectory()` ilə nümunə yol nişanı qovluğunu
silir. Testdə disk saxtalaşdırılmadığı üçün əmr **produksiyadakı** qovluğa düşürdü.

- `DemoContentTest`-ə **`Storage::fake('public')`** əlavə olundu — test artıq real fayl
  sisteminə toxunmur. Şəkillər seeder ilə bərpa edildi (16 SVG, `200 image/svg+xml`).
- **URL artıq əl ilə birləşdirilmir.** Şablonlarda beş yerdə `` `/storage/${path}` `` vardı;
  hamısı `Question::imageUrl()` / `QuestionOption::imageUrl()`-a keçdi, onlar da
  **`Storage::disk('public')->url()`** işlədir. Disk konfiqurasiyası (`APP_URL`, disk `url`,
  CDN) dəyişsə şablonlar sınmır. Model bütöv göndəriləndə `question_image_url` avtomatik
  əlavə olunur (`$appends`), ona görə admin redaktə formaları da yolu özü qurmur.
- Dəyişən səhifələr: imtahan verilişi, nəticə, admin qiymətləndirmə, admin imtahan səhifəsi,
  admin və müəllim sual formaları.

**Alt mətni cavabı açırdı.** "Dairəvi nişan: qırmızı fon, ortasında geniş ağ üfüqi zolaq"
birbaşa "giriş qadağandır" deməkdir — şəkil açılmayanda (və ya ekran oxuyucusunda) şagird
cavabı elə təsvirdən tapırdı.

- Nümunə suallarda alt mətni **neytraldır**: yalnız **"Yol nişanı"** və ya
  **"Yolayrıcı sxemi"**.
- Admin formasındakı izah gücləndirildi: alt mətni **yalnız şəklin NÖVÜNÜ** bildirməlidir,
  məzmununu yox; düzgün və səhv nümunələr göstərilir.

**Testlər:** nümunə şəkillərin alt mətni variant mətnlərinin heç birini təkrarlamır və
nişanın adını daşımır; şəkil URL-i `Storage` diskindən qurulur, şəkli olmayan sualda null
qaytarır (487 → 489 test).

---

### 2026-09-23 — Nəticə səhifəsinin düzəlişləri və açıq cavabların AI ilə qiymətləndirilməsi

**Nəticə səhifəsi (səhv düzəlişləri).**

- **"Qrup: Tarix: 2026-09-23 12:06"** — `attempt.group?.name` boş idi, çünki MİQ/sürücülük
  imtahanlarında `group_id` artıq NULL-dur; iki sətir vizual olaraq birləşirdi. İndi qrup
  varsa qrup, yoxdursa **bölmə adı** göstərilir, tarix ayrıca sətirdədir.
- **İki fərqli faiz** yan-yana dururdu: nisbi bal (NB) və `düz/cəmi` faizi ("13% düzgün").
  İkincisi **saya** çevrildi — "8 sualdan 1-i düzgün". NB əsas göstərici qaldı.
- **İlkin bal:** açıq suallar yoxlanmayıbsa rəqəm neytral boz, yanında **"ilkin"** nişanı;
  yekun bal yalnız yoxlamadan sonra vurğulanır.
- **Açıq suallar artıq görünür:** şagirdin cavabı, `open_coded` üçün qəbul olunan cavablar,
  `open_written` üçün meyar, verilən qiymət (⅓, ½ …), bal və əsaslandırma. Əvvəl şablon
  yalnız variantları render edirdi — məlumat payload-da var idi, istifadə olunmurdu.

**Açıq cavabların avtomatik qiymətləndirilməsi.** Yalnız `open_written`; `open_coded`
`AnswerNormalizer`-dədir və AI-yə göndərilmir.

- `questions.grading_rubric` — düzgün cavab və meyarlar. Admin formasında (yalnız açıq yazılı
  tipdə) və Excel importunda (`meyar` sütunu). **Meyarı olmayan sual AI-yə göndərilmir** —
  meyarsız qiymət uydurma olardı.
- Cəhd bitəndə hər açıq cavab üçün `GradeOpenAnswer` job-u `ai-grading` növbəsinə düşür.
  Sorğu **Laravel `Http` fasadı** ilə birbaşa Messages API-yə gedir — **yeni composer
  asılılığı əlavə edilmədi**, versiya uyğunsuzluğu riski yoxdur.
- Cavab DİM şkalasında (0, ⅓, ½, ⅔, 1) qiymət və 1–2 cümləlik əsaslandırma qaytarır.
  Struktur iki səviyyəlidir: `output_config.format` (json_schema) **və** promptda "yalnız
  JSON qaytar"; format işləməsə mətndən JSON çıxarılır. **Parse edilməyən və ya şkalaya
  düşməyən cavab rədd olunur** — sual əl ilə yoxlamaya qalır.
- Hamısı qiymətlənəndə bal yenidən hesablanır və status `completed` olur.
- **Model:** defolt `claude-sonnet-5` (qısa cavablar üçün kifayətdir, xərci Opus-dan
  qat-qat azdır). `AI_GRADING_ESSAY_MODEL` ilə uzun cavablar üçün ayrıca model təyin edilir.
- **Prompt injection:** şagirdin mətni `<sagird_cavabi>` teqləri arasındadır, sistem promptu
  həmin blokdakı təlimatlara **əməl etməməyi** açıq tapşırır. Mətn config-dəki hədə qədər
  kəsilir və kəsildiyi modelə bildirilir (səssiz kəsmə yoxdur).
- **Xəta halında sistem sınmır:** açar yoxdursa, API xəta verirsə, model imtina edirsə və ya
  cavab yararsızdırsa — cavab `pending_review` qalır, hadisə loglanır, job "failed" sayılmır.
- **AI qiyməti son deyil:** admin növbədə qiyməti, əsaslandırmanı, modeli və token sayını
  görür; başqa qiymət seçəndə `grade_source = admin` olur və AI-nikini üstələyir.
- **Şagird etirazı:** "İlkin qiymət avtomatik verilib" izahı + **"Yenidən baxılsın"** düyməsi.
  Bal dəyişmir, status oynamır — yalnız `review_requested_at` qoyulur və cavab adminin
  növbəsində "Etiraz edilib" nişanı ilə çıxır. Admin qiymət verəndə etiraz bağlanır.
- **Xərc nəzarəti:** hər cavabda model və token sayları (uğursuz sorğularda da — pul onda da
  yanır); admin qiymətləndirmə səhifəsində ümumi göstərici.

**Növbə infrastrukturu.** Yoxladım: `QUEUE_CONNECTION=database`, amma **işləyən worker yox
idi** — növbəyə qoyulan iş heç vaxt icra olunmazdı. Serverdə **supervisor quraşdırılmayıb**
(34 sayt, PID 1 systemd-dir), ona görə işçi **systemd xidməti**dir: yeni paket lazım deyil,
xidmət yalnız bu sayta aiddir, `MemoryMax=256M` ilə məhdudlanıb.

- `deploy/teacher-queue.service` — xidmət faylı (repoda, **serverə tətbiq edilməyib**).
- `deploy/crontab.txt` — bu saytda olmayan `schedule:run` sətri.
- **DEPLOY.md:** deploy addımlarına **7-ci addım `php artisan queue:restart`** əlavə olundu —
  `queue:work` kodu yaddaşda saxlayır, restart olmadan işçi köhnə kodla qalır.

**Açar:** `.env` və `.env.example`-a boş `ANTHROPIC_API_KEY=` sətri əlavə olundu. Açar repoda,
logda və hesabatda **saxlanılmır**.

**Testlər:** `AiGradingTest` (15) — API mock-lanır: uğurlu qiymət və yenidən hesablama,
şkalaya düşməyən cavab, parse edilməyən cavab, API xətası, açarsız iş, meyarsız sual,
növbənin idempotentliyi, prompt injection izolyasiyası, uzun cavabın kəsilməsi, adminin
üstünlüyü, şagird etirazı (472 → 487 test).

---

### 2026-09-23 — Filtr panelinin sürüşməsi, şəkilli suallar və sual tipi qaydaları

**Filtr paneli (səhv düzəlişi).** `/imtahanlar`-da yan panel səhifə ilə birlikdə yuxarı
gedirdi: istifadəçi filtrə çatmaq üçün bütün nəticə siyahısını aşağı sürüşdürməli olurdu.
Səbəb panelin ekrandan hündür olması idi — `position: sticky` yalnız elementin altı görünəndə
işləyir. İndi panel `max-height: calc(100vh − boşluq)` ilə məhdudlanır və **öz daxilində**
sürüşür (`overflow-y: auto`, `overscroll-behavior: contain`). Mobildə açılan panel davranışı
dəyişmədi.

**Şəkilli suallar tamamlandı.** Sual şəkli əvvəlcə yalnız imtahan verilişi səhifəsində
görünürdü; variant şəkilləri heç yerdə, nəticə və qiymətləndirmə səhifələrində isə şəkil
ümumiyyətlə yox idi.

- Ortaq `QuestionImage.vue` komponenti: imtahan, nəticə və admin qiymətləndirmə səhifələrində
  işlənir; mobildə responsivdir (`max-width: 100%`, `height: auto`).
- **Variant şəkilləri** (`question_options.option_image`) artıq imtahanda və nəticədə görünür.
- Yeni `questions.question_image_alt` sütunu: şəkilli sualda şəkil məzmunun özüdür, ona görə
  ekran oxuyucusu üçün təsvir lazımdır. Admin formasında şəkil yüklənən kimi sahə açılır,
  izahı ilə birlikdə: təsvir cavabı verməməlidir ("üçbucaq nişan, içində əyri ox" olar,
  "təhlükəli döngə nişanı" olmaz).

**Nümunə yol nişanları.** `DemoRoadSigns` 12 standart nişan və 4 yolayrıcı sxemini **SVG
olaraq generasiya edir** — xarici şəkil yüklənmir, fayllar deterministikdir və `demo:clear`
qovluğu bütöv silir. Sürücülük suallarının **24-ü (32-dən) şəkillidir**: nişanın mənası,
nişanın qrupu və "kim birinci keçir" sxemləri. Nişanın üzərində adı yazılmır — sual öz
cavabını vermir.

**İmtahan növünə görə icazəli sual tipləri.** Yeni `config/questions.php` və
`App\Support\QuestionTypes` (ən uzun prefiksə görə uyğunluq):

| Kateqoriya | İcazəli tiplər |
|---|---|
| Sürücülük | yalnız qapalı |
| MİQ, sertifikasiya, diaqnostik, məktəbəqədər | yalnız qapalı |
| Dövlət qulluğu — BB və AC | yalnız qapalı |
| Dövlət qulluğu — BA, AB, AA | qapalı + yazılı |
| Magistratura, buraxılış, I və II mərhələ | hər üç tip |

Tətbiq nöqtələri: **admin sual forması** (yalnız icazəli növ göstərilir və serverdə
`StoreQuestionRequest` yoxlayır), **bankdan generasiya** (`ExamGenerator` icazəsiz tipi
hovuza salmır) və **nümunə məzmun seeder-i**. Sürücülük və müəllim imtahanlarındakı açıq
suallar qapalı ilə əvəzləndi — seeder qaydaya uyğun qapalı çərçivələr qurur (`pairing`,
`quarterClosed`, `passageAlt` əlavə olundu ki, açıq tip söndürüləndə də səkkiz FƏRQLİ sual
çıxsın; əvvəl mətn təkrarlanırdı).

**ROADMAP P3:** MİQ-in öz bal sistemi (ixtisas 2 bal, metodika 1 bal; səhvdə −0,5 / −0,25)
və magistratura essesi (95 + 5) üçün ayrıca `ScoringStrategy`.

**Testlər:** `QuestionTypeRulesTest` (5) — config, admin forması, generasiya;
`DemoContentTest`-ə iki yeni test — şəkilli sürücülük sualları və nümunə imtahanların
tip qaydasına uyğunluğu (465 → 472 test).

---

### 2026-09-23 — Test ödənişi, başlıqda hesab, kataloq sadələşməsi və rəng dili

**Ödəniş test rejimi.** Sayt müvəqqəti subdomendə olduğu üçün `PAYMENT_DRIVER=fake` artıq
**produksiyada da işləyir**: "Al" basılanda bank səhifəsi açılmır, ödəniş dərhal `paid` olur,
giriş açılır və şagird imtahana başlaya bilir. Ödəniş qeydində `provider = fake`,
`payload.test_mode = true`. Səhifədə **"Test rejimi — real ödəniş getmir"** xəbərdarlığı var.

- Tək açar `PAYMENT_DRIVER`: real driver yazılanda test rejimi bir addımla sönür.
  `PaymentGatewayFactory`-dəki `APP_ENV` şərti götürüldü, `testMode()` əlavə olundu.
- Uğursuz axını əl ilə yoxlamaq üçün sınaq bank səhifəsi qalır, adi axında işlədilmir.
- **ROADMAP P2.5 və TESTING.md:** öz domenimizə keçməzdən əvvəl `PAYMENT_DRIVER` real
  provayderə dəyişdirilməlidir — əks halda ödənişli imtahanlar faktiki olaraq pulsuz olar.
- "Onlayn alış bağlıdır, bizimlə əlaqə saxla" → **"Onlayn ödəniş tezliklə aktivləşəcək."**

**Başlıqda hesab vəziyyəti.** `SiteHeader.vue` həmişə "Daxil ol / Qeydiyyat" göstərirdi,
halbuki `auth.user` səhifəyə onsuz da göndərilirdi. İndi daxil olmuş istifadəçidə inisiallı
düymə və açılan menyu var: Mənim imtahanlarım, Nəticələr, Statistika, Profil, Çıxış; admin
hesabında əlavə **Admin panel**. Bəndlər **rola görə** seçilir (şagird bölmələri yalnız şagird
rolunda — əks halda link 403-ə aparardı). Çıxış `method="post"`. Mobil paneldə eyni siyahı;
Escape və kənara klik menyunu bağlayır.

**`/imtahanlar` sadələşdirildi.**

- **Defolt görünüş qruplaşdırılmışdır**: hər kök bölmə üçün başlıq, 4 kart və "Hamısına bax (N)".
  Beləcə sürücülük kateqoriyaları kimi böyük bölmə bütün səhifəni tutmur. Filtr və ya axtarış
  seçiləndə səhifələnən düz siyahıya keçilir. **Sıralama görünüşü dəyişmir** — yalnız
  bölmələrin içindəki sıranı dəyişir.
- **Sıralama** (`?sirala=`): ən yeni (defolt), əvvəlcə pulsuz, ucuzdan bahaya, bahadan ucuza.
- Filtr paneli yığcamlaşdı: kateqoriya **ikisəviyyəli akkordeon**, fənn siyahısı 6-dan sonra
  "Daha çox (N)". Seçilmiş filtrlər panelin üstündə **silinə bilən çip** kimi, yanında
  "Hamısını sıfırla".
- "Tədris dili" axtarışın üstündən filtr panelinin ən yuxarısına keçdi və adı aydınlaşdı:
  **"İmtahanın dili (sektor)"** + izah — başlıqdakı AZ|RU (interfeys dili) ilə qarışmır.

**İmtahan kartı yenidən quruldu.** Üst sətir bölmə yolu (`Sürücülük › DE kateqoriyası`, rəngli
nöqtə ilə), başlıq yalnız növ və rüb, alt sətir fənlər/sual sayı/müddət, aşağıda qiymət və ya
"Pulsuz" nişanı. İmtahanın öz adı kartda təkrarlanmır (o, çox vaxt kateqoriya + növ sözünün
təkrarı idi). Klik sahəsi **stretched link** üsulu ilədir: kart adi blokdur, link başlıqdadır
və `::after` ilə bütün kartı örtür — fokus başlığa düşür, link mətni qısa qalır, `aria-label`
isə bölmə yolunu da əlavə edir ki, ekran oxuyucusunda linklər seçilsin.

**Rəng və vizual iyerarxiya.**

- **`categories.color`** (migration + admin formasında rəng seçici): kök bölmənin rəngi, alt
  düyünlər onu miras alır (`Category::displayColor()`). Kartın üst zolağı, bölmə nöqtəsi və
  filtr paneli həmin rəngdədir. Rənglər səhifələrdə `--cat` CSS dəyişəni kimi toplanır —
  qlobal stil faylına toxunulmadı.
- Palitra (kontrast kağız fonunda, hamısı **WCAG AA ≥4.5:1**): Orta məktəb `#2440A0` (8.83),
  Abituriyent `#C8354E` (5.02), Magistratura `#6B3FA0` (7.19), Dövlət qulluğu `#1B6B44` (6.32),
  Müəllimlər `#9A4B06` (6.04), Sürücülük `#0F766E` (5.33), Digər `#596069` (6.19).
- "Pulsuz" yaşıl nişan (`#1F7A4D` / `#E6EFEA` = 4.53), qiymət `--paper-sunk` fonunda vurğulu,
  növ üçün rəngli etiket. **Rəng heç yerdə tək məlumat daşıyıcısı deyil** — hamısında mətn var.
- İmtahan səhifəsində beş boz qutu getdi: müddət, sual sayı və maksimal bal ikonlarla bir
  sətirdə, qiymət və əsas düymə sağda vurğulu blokda.
- İyerarxiya: h1 1.75–2rem/700 → bölmə başlığı 1.25rem/700 → kart başlığı 1.0625rem/600 →
  meta 0.9375rem muted.

**Demo məzmun şagird tərəfdə gizləndi.** İmtahan adlarından və sual mətnlərindən `[DEMO]`
prefiksi, imtahanlardan isə "Demo imtahan…" izahı çıxarıldı — nümunə məzmun real məzmundan
seçilmir. `is_demo` bayrağı qalır (`demo:clear` ona görə işləyir) və **admin paneldə**
(imtahan siyahısı, sual bankı) kiçik sarı `demo` nişanı ilə göstərilir. Slug-lardakı `demo-`
hissəsi dəyişmədi: mövcud ünvanlar qırılmadı.

**Testlər:** `SiteHeaderTest` (5), `ExamCatalogTest` genişləndi (22) — qruplaşdırılmış görünüş,
sıralama variantları, filtrin tək-tək silinməsi, kartın bölmə yolu və rəngi; `ExamPurchaseTest`
test rejimi axınına uyğunlaşdı (al → paid → giriş → başla), callback testləri gözləyən ödənişi
birbaşa qurur (452 → 465 test).

---

### 2026-09-23 — `/imtahanlar` kataloqu və mobile-first

**Ümumi kataloq.** `/imtahanlar` (rusca `/ru/imtahanlar`) — kateqoriya ağacından asılı olmayan
giriş nöqtəsi: bütün dərc olunmuş imtahanlar, **ən yenisi əvvəldə**, səhifə başına 24 imtahan.

- **Filtrlər kateqoriya səhifəsi ilə ORTAQDIR.** Frontend: `Components/Catalog/CatalogFilters.vue`.
  Backend: `App\Support\CatalogFilters` — `CategoryController`-in private metodları oraya
  köçdü, yəni iki səhifə heç vaxt fərqli nəticə vermir. Kataloqda əlavə olaraq **kateqoriya**
  (kök + ikinci səviyyə, alt ağac sayğacı ilə) və **ada görə axtarış** var.
- Seçim URL-də qalır (`?kateqoriya=8&nov=topic_trial&rub=2&fenn=3&qiymet=pulsuz&axtar=…&sehife=2`),
  süzülmüş səhifə paylaşıla bilir. Filtr dəyişəndə birinci səhifəyə qayıdılır.
- **Sayğaclar ƏHATƏ üzrədir**: seçilmiş çip digər ölçülərin sayğaclarını daraltmır — əks halda
  hər kliklə çiplər yerini dəyişər və nəticəsi sıfır olan dalan yaranardı.
- Sektor qaydası burada da işləyir; kartlar `/imtahan/{slug}`-a aparır.
- **SEO:** `/imtahanlar` ↔ `/ru/imtahanlar` canonical + hreflang cütü, sitemap-da statik
  səhifələrlə birlikdə. **Filtrli və səhifələnmiş ünvanların canonical-ı filtrsiz səhifəyə
  göstərir** (`Localization::seo()` canonical-ı query string-siz yoldan qurur), yəni eyni
  məzmun onlarla ünvanda indeksləşmir. `SEO_INDEXING`-ə tabedir.
- Başlıq menyusuna **"İmtahanlar"** linki; ana səhifədəki kateqoriya kartları olduğu kimi qalır.

**Mobile-first** — `/imtahanlar`, kateqoriya və imtahan səhifəsi:

- **Filtrlər** <768px-də açılıb-bağlanan panel (açar düymədə aktiv filtr sayı), ≥768px həmişə
  açıq, `/imtahanlar`-da ≥1024px **yan sütunda** (yapışqan).
- **Kartlar** 1 sütun → ≥640px 2 → ≥1024px 3. Grid övladlarına `min-width: 0` — uzun başlıq
  sütunu genişləndirib üfüqi sürüşmə yaratmır.
- **Toxunma sahələri 44px**: çiplər, sektor düymələri, səhifələmə, hamburger. Mətn ölçüsü
  dəyişmədi — sahə yalnız `padding` ilə böyüdü.
- **Daxiletmə sahələri 1rem** (axtarış) — iOS Safari 16px-dən kiçik sahədə səhifəni avtomatik
  yaxınlaşdırır. İkinci dərəcəli mətnlər mobildə 15px qaldı, vizual iyerarxiya pozulmadı.
- **Başlıq <560px-də** naviqasiyanı hamburger panelinə yığır: 360px-də brend (~180px) +
  AZ|RU (96px) + açar (44px) onsuz da konteynerin hamısını tutur. Escape panel bağlayır.
- `MathText.vue` KaTeX konteyneri öz `overflow-x: auto` qabında — uzun `$$…$$` düsturu
  səhifəni yana sürükləmir.
- 360px statik CSS nəzərdən keçirməsi: sabit en, `min-width: auto` tələsi və `nowrap` halları
  yoxlandı; `.chip`-ə `max-width: 100%` + `overflow-wrap: anywhere`, `.cta-hint`-ə
  `min-width: 0` əlavə olundu.

**Testlər:** `tests/Feature/Catalog/ExamCatalogTest.php` (13 test) — sıralama, səhifələmə,
hər filtr ayrıca və birləşmiş, axtarış, sayğaclar, sektor, canonical/hreflang, sitemap;
`RouteRegistrationTest`-ə `/imtahanlar` və `/ru/imtahanlar` (catch-all-a düşməsin).
Mövcud `CatalogFilterTest` dəyişmədən keçir — ortaq sinfə keçidin reqressiya qoruyucusu
(437 → 452 test).

---

### 2026-09-23 — Demo məzmun, `is_demo` bayrağı və qrupsuz imtahanlar

**Demo məzmun.** Kataloqun heç bir düyünü və heç bir filtri boş qalmasın deyə
`DemoContentSeeder` əlavə olundu (`php artisan db:seed --class=DemoContentSeeder --force`).

- **Əhatə:** `DemoCatalog`-dakı 36 düyünün hər birinə 4 imtahan — **ümumi sınaq**,
  **mövzu sınağı** (rüb düyündən-düyünə dəyişir), **fənn sınağı**, **məşq testi**. Beləcə
  NÖV filtrində dörd variantın, QİYMƏT filtrində isə hər iki variantın (yarısı pulsuz,
  yarısı 3–10 AZN) hamısı hər düyündə görünür.
- **Rus sektoru:** `ru_enabled` olan 15 düyündə eyni dəst `sector = ru` ilə də qurulur,
  adları və sualları rusca.
- **Sual bankı:** hər fənn üçün rüblərə bölünmüş 4 mövzu və hər mövzuya 8 sual. Növlər
  qarışıq — qapalı (4 və 5 variantlı), açıq kodlaşdırılan, açıq yazılı; bir hissəsində
  hesablanmış **KaTeX düsturu** (`DemoFormulas` — cavablar həqiqətən doğrudur), bir
  hissəsində uzun situasiya/oxu mətni. Suallar taksonomiyadan qurulduğu üçün cavabları
  yoxlanıla biləndir, hamısı `[DEMO]` ilə başlayır.
- **Demo şagirdlər:** hər sektor üçün bir hesab (`demo.az@example.test`, `demo.ru@example.test`),
  `is_demo` işarəli. Parol hər işə salmada təsadüfi qurulur və **yalnız seeder çıxışında**
  göstərilir. Cəhdlər real axınla yaradılır (sual siyahısı dondurulur) və **`AttemptScorer`**
  ilə qiymətləndirilir — ballar uydurulmur. Bir neçə cəhd yoxlanmamış yazılı cavabla qalır
  ki, admin qiymətləndirmə ekranı da boş olmasın.
- **İdempotentlik:** mövzu `subject_id + slug`, sual `source` (`DEMO:fənn:dil:rüb:nömrə`),
  imtahan `slug` açarı ilə tapılır. İkinci işə salmada heç nə yaradılmır.
- **Təhlükəsizlik:** produksiyada xəbərdarlıq verib dayanır, `--force` və ya açıq təsdiq
  tələb edir. `DatabaseSeeder`-ə **qoşulmayıb** — adi `db:seed` onu çağırmır.
- **Hesabat:** seeder sonda kateqoriya üzrə imtahan sayını (alt ağac, az/ru ayrıca) və
  boş qalan filtr variantlarını cədvəldə göstərir.

**`is_demo` bayrağı** (`exams`, `questions`, `topics`, `users`) və **`php artisan demo:clear`**:
demo imtahan, bölmə, sual, variant, mövzu, cəhd, cavab, giriş hüququ, ödəniş və demo hesabları
silir. `--dry-run` nə silinəcəyini göstərir. Real data qorunur: demo sual REAL imtahanda
işlənibsə, demo mövzuya REAL sual bağlıdırsa — saxlanılır və hesabatda bildirilir. Cəhdlər
ayrıca bayraq daşımır, demo imtahana və ya demo şagirdə bağlılıqla tapılır.

**Yeni fənlər** (demo deyil, real): **Yol hərəkəti qaydaları** və **Kurikulum və metodika**.
`category_subject` ilə sürücülük və müəllim kateqoriyalarına bağlandı. Heç bir DİM qrupuna
bağlanmadığı üçün `subject_group_scores` matrisinə təsir etmir — balları pivotdan gəlir.
MİQ və sertifikasiya imtahanları **ikibölməli** qurulur: müəllimin öz fənni (imtahandan-imtahana
dəyişir) + Kurikulum və metodika.

**Qrupsuz imtahanlar.** `exams.group_id` və `exam_attempts.group_id` artıq **nullable**-dır
(`cascade` → `nullOnDelete`). DİM bal qrupları yalnız abituriyent qəbuluna aiddir; sürücülük,
MİQ, sertifikasiya, magistratura və dövlət qulluğu imtahanına əvvəllər uydurma qrup (I qrup)
yazılırdı və bu, statistikada səhv qruplaşdırma yaradırdı.

- `AdminExamController`: qrup kateqoriyadan götürülür — kateqoriyanın qrupu yoxdursa sahə
  NULL qalır (formada seçim edilsə belə). Admin formasında qrup artıq məcburi deyil ("Qrupsuz").
- **Cərimə əmsalı qrupdan ayrıldı:** `ScoringInput::$stage` nullable oldu, qrupsuz imtahanda
  `scoring.penalty_without_group` (= 0) işləyir. Keçici həll — hər kateqoriya üçün ayrıca
  `ScoringStrategy` ROADMAP P3-dədir, kodda da qeyd olunub.

**Testlər:** `tests/Feature/DemoContentTest.php` — əhatə, rus sektoru, qrup qaydaları, sual
növlərinin qarışığı, demo şagirdlərin hesablanmış cəhdləri, idempotentlik və `demo:clear`-in
real dataya toxunmaması (430 → 437 test).

---

### 2026-09-22 — İmtahanın tək əsas ünvanı və panelin sadələşdirilməsi

**SEO.** İmtahan məzmunu tərcümə olunmur, ona görə hər imtahanın **tək əsas ünvanı** var:
az sektoru üçün `/imtahan/{slug}`, rus sektoru üçün `/ru/imtahan/{slug}`.

- Digər dil prefiksi ilə səhifə açılmağa davam edir (interfeys dili üçün), amma `canonical`
  həmişə əsas ünvana göstərir və imtahan səhifələrində **`hreflang` alternativi yazılmır** —
  eyni məzmun iki ünvanda indeksləşməsin.
- Sitemap-a hər imtahan **bir dəfə**, yalnız əsas ünvanı ilə düşür (`urlNode`-a
  `withAlternates` parametri əlavə olundu).
- `Localization::CANONICAL_ATTRIBUTE`: səhifə tək əsas ünvanını bildirəndə `seo` prop-u
  `alternates: []` və `x_default: null` qaytarır; `SeoHead.vue` və `partials/seo.blade.php`
  x-default-ı yalnız mövcud olanda yazır.

**Şagird paneli.** Heç vaxt doldurulmayan "Mövcud İmtahanlar" bloku çıxarıldı (controller
`availableExams` propunu göndərmirdi, blok həmişə "Hazırda mövcud imtahan yoxdur" yazırdı).
Panel indi **davam edən cəhdləri** (qalan vaxt ilə), **son beş nəticəni** (nisbi bal və düz
cavab sayı ilə) və başlıqda **"Kataloqa keç"** düyməsini göstərir. Nəticə sətirləri artıq
mövcud olmayan `attempt.score`/`total_questions` sahələrinə baxmır.

Tövsiyə bloku P3-dəki **"Məqsədim"** funksiyası ilə gələcək — ROADMAP-da həmin bəndə yazıldı.

**Testlər:** 430 test / 2174 assertion.

### 2026-09-22 — Vahid imtahan axını: kataloq → imtahan səhifəsi → başlama

Şagirdin imtahan tapıb alması üçün iki yarımçıq kataloq var idi: kateqoriya səhifələrindəki
kartlar hamısı `/login`-ə aparırdı, `/student/exams` isə yalnız fənn/qrup filtri ilə işləyirdi.
İndi axın birdir.

- **İctimai imtahan səhifəsi `/imtahan/{slug}`** (`ExamController`, `Exam/Show.vue`): kateqoriya
  zənciri, bölmələr üzrə fənn və sual sayı, müddət, bal, qiymət. Qonağa da açıqdır.
  Kabinetdəki `student.exams.show` **silindi** və 301 ilə bura yönləndirilir.
- **Düymə vəziyyətə görə dəyişir**: qonaq → giriş, girişi olan şagird → "Başla", girişi olmayan
  → "Al", davam edən cəhd → "Davam et". Qonaq girişdən sonra **həmin imtahan səhifəsinə**
  qayıdır (`exam.enter` route-u `auth` altındadır, intended URL-i Laravel saxlayır) və
  **cəhd avtomatik başlamır** — taymer yalnız şagird "Başla"nı təsdiqləyəndə işə düşür.
- **Kateqoriya səhifəsi əsas kataloqdur**: növ, rüb, fənn və qiymət filtrləri əlavə olundu,
  seçim URL-də query kimi qalır (`?nov=topic_trial&rub=2&fenn=3&qiymet=pulsuz`), hər variantın
  yanında sayğac var. **Fənn filtri `exam_sections.subject_id`-ə baxır** — çoxfənli imtahan
  içindəki hər fənnə görə tapılır.
- **Kabinet "Mənim imtahanlarım"a çevrildi**: davam edən cəhdlər, girişi olan imtahanlar və
  tamamlanmış nəticələr; köhnə filtrli siyahı silindi, boş halda "Kataloqa keç" düyməsi var.
- **`exams.slug`**: `App\Support\Slug` açıq AZ hərf xəritəsi ilə (ə→e, ı→i, ö→o, ü→u, ş→s,
  ç→c, ğ→g) — `Str::slug`-ın defolt davranışına güvənilmir. Slug yalnız imtahan yaradılanda
  qurulur: başlıq düzəldiləndə paylaşılmış ünvan sınmır.
- **SEO**: imtahan səhifəsi canonical/hreflang və `BreadcrumbList` JSON-LD alır, sitemap-a düşür
  və `SEO_INDEXING` bayrağına tabedir.

**Testlər:** 425 test / 2119 assertion (yeni `Catalog/ExamPageTest`: 9, `Catalog/CatalogFilterTest`: 11,
`Catalog/MyExamsTest`: 8, `Unit/SlugTest`: 22).

### 2026-09-22 — Şagird panelindəki göstəricilər düzəldildi

Panel "Orta Bal" sütununda `45.000000` göstərirdi: `StudentDashboardController` xam
`avg('total_score')` işlədirdi, MySQL isə DECIMAL üçün orta qiyməti dörd əlavə onluqla
qaytarır. "Ən Yüksək Bal" və "Düzgün Cavab %" isə controller tərəfindən **ümumiyyətlə
göndərilmirdi** — Vue `|| 0` ilə həmişə sıfır çıxarırdı.

- Panel indi statistika səhifəsi ilə **eyni mənbədən** (`StudentStatistics::overview()`)
  qidalanır: nisbi bal (100-lük), bir onluğa yuvarlaqlaşdırılmış.
- `overview()`-a `correct_percentage` əlavə olundu: düz cavabların cavablandırılmış suallara
  nisbəti (boş buraxılanlar da məxrəcdədir); cəhd yoxdursa `null`.
- Başlıqlar statistika səhifəsi ilə uyğunlaşdırıldı: "Orta nəticə (100-lük)",
  "Ən yüksək (100-lük)".

**Testlər:** 368 test / 1778 assertion (yeni `StudentDashboardTest`: 4).

### 2026-09-22 — Ayrıca müəllim girişi silindi

`TeacherLoginController` və `Teacher/Auth/Login.vue` silindi: vahid `/login` bütün rolları
qəbul etdiyi üçün ikinci giriş forması lazım deyil.

- Modul açıq olanda `/teacher/login` (GET və POST) **301** ilə `/login`-ə yönləndirilir —
  yadda qalmış linklər və köhnə formalar sınmır, sessiya isə orada açılmır.
- `teacher.logout` route-u silindi; çıxış vahid `/logout`-dadır.
- `redirectGuestsTo`-dan `teacher.*` budağı çıxarıldı: müəllim səhifəsinə girən qonaq birbaşa
  `/login`-ə gedir (əvvəl `/teacher/login` üzərindən iki hop olurdu).
- Altlıqdakı və ana səhifədəki "Repetitor girişi" linkləri götürüldü (`site.footer.tutor_login`,
  `landing.tutors.login` açarları və işlənməyən CSS ilə birlikdə). "Repetitor qeydiyyatı"
  linki qalır — o, daxil olmuş hesaba müəllim rolu əlavə edir.

**Testlər:** 364 test / 1723 assertion.

### 2026-09-22 — Auth refaktorunun üç düzəlişi

- **Admin girişi səbəbi açmır.** Admin olmayan hesab düzgün parolla da səhv parolla eyni
  mesajı alır (`AdminLoginRequest::FAILED_MESSAGE`) — əvvəlki "Bu hesab admin deyil" cavabı
  parolun tapıldığını bildirirdi.
- **`/admin/login` daxil olmuş istifadəçini qovmur.** `guest` əvəzinə
  `RedirectAdminsToDashboard`: yalnız artıq admin olan panelə yönləndirilir, başqa rolla
  daxil olan formanı görür və oradan admin hesabına keçə bilir.
- **Paneli olmayan hesab 403 almır.** Müəllim modulu söndürülü olanda yalnız `teacher` rolu
  olan (və ya heç bir rolu olmayan) hesab `/panel-yoxdur` səhifəsinə düşür: səbəb izah olunur,
  rollar və "Çıxış" göstərilir. `Panel::hasPanel()` bu halı bir yerdə müəyyən edir.

**Testlər:** 364 test / 1725 assertion (9 yeni bənd `RolePanelTest`-də).

### 2026-09-22 — Auth sadələşdirilməsi: tək guard, bir hesab / bir neçə rol

Ayrı `admin`, `teacher`, `student` guard-ları **silindi**. İndi tək `web` guard-ı və tək `users`
cədvəli var; rol spatie ilə verilir, panellər `auth` + rol middleware-i ilə qorunur
(`EnsureUserIsAdmin/Teacher/Student` — hamısı `$request->user()` ilə işləyir).

- **Bir hesabın bir neçə rolu ola bilər.** Vahid `/login` artıq rola görə rədd etmir: girişdən
  sonra hesab öz panelinə düşür — admin → `/admin/dashboard`, müəllim (modul açıq olanda) →
  müəllim paneli, qalanlar → şagird kabineti (`App\Support\Panel::homeUrl()`).
- **Başqa rolun panelinə girəndə 403** (əvvəl giriş səhifəsinə yönləndirilirdi).
- **`/admin/login` ayrıca səhifə kimi qalır**, amma eyni `web` sessiyası ilə işləyir, yalnız admin
  rolunu buraxır və **sürət limiti** var (`AdminLoginRequest`, email+IP üzrə 5 cəhd). Parol düz
  olsa da admin olmayan hesabın sessiyası dərhal bağlanır.
- **Qeydiyyat:** `/register` həmişə `student` rolu verir. Müəllim qeydiyyatı (modul açılanda)
  artıq **yeni hesab yaratmır** — daxil olmuş hesaba `teacher` rolu, `teacher_profile` və fənlər
  əlavə edir, şagird rolu isə qalır.
- **`EXAM_OWNER_ID` silindi.** İmtahanın sahibi `exams.created_by` sütunudur (istənilən rolda
  istifadəçi); `teacher_id` nullable qalır və yalnız müəllim modulunda işlənir. Köhnə imtahanlar
  üçün `created_by` migration zamanı `teacher_id`-dən doldurulur.
- `HandleInertiaRequests` və `SetLocale` artıq guard-ları bir-bir yoxlamır: `auth()->user()` və
  rolları paylaşır. Frontend menyusu da `auth.guard` yerinə rollara baxır.

**Testlər:** 355 test / 1667 assertion (yeni `RolePanelTest`: 10, `TeacherModuleAuthTest`: 8).

### 2026-09-21 — `SEO_INDEXING` bayrağı: sayt müvəqqəti domendə indeksləşmir

`.env`-də bir bayraq bütün SEO davranışını idarə edir (`config/seo.php`). **Produksiyada
`false` qoyulub** — əsl domenə keçəndə `true` ediləcək.

`SEO_INDEXING=false` olanda (`APP_ENV`-dən asılı olmayaraq):

- hər səhifə `<meta name="robots" content="noindex, nofollow">` alır (Blade + SeoHead.vue,
  yəni həm ilk yüklənmədə, həm SPA keçidində);
- hər cavaba `X-Robots-Tag: noindex, nofollow` başlığı əlavə olunur (bütün route-lar);
- `robots.txt` → `User-agent: *` + `Disallow: /`, **sitemap göstərilmir**.

`true` olanda əvvəlki davranış qayıdır: `robots.txt` yalnız panel ünvanlarını bağlayır və
sitemap-ı göstərir. İndeksləşmə yalnız bayraq açıq **və** `APP_ENV=production` olanda işləyir —
staging/lokal nüsxə həmişə bağlıdır.

**`robots.txt` artıq statik fayl deyil**, route-dur (`RobotsController`): `public/robots.txt`
silindi, əks halda veb server statik faylı verərdi və bayraq işləməzdi.

**Testlər:** 340 test / 1625 assertion (yeni `SearchIndexingTest`: 9 test).

### 2026-09-21 — Deploy alətləri və staging hazırlığı (P2.5, kod tərəfi)

- **`php artisan db:backup`**: bazanın nüsxəsi `public_html`-dən kənarda saxlanılır, yalnız son
  3 nüsxə qalır, fayl icazəsi 600-dür və parol əmr sətrində görünmür (`MYSQL_PWD`).
- **`php artisan staging:anonymize`**: staging surətində şagird/müəllim adı, emaili, telefonu və
  parolu dəyişdirilir. Produksiyada işləmir, admin hesablarına toxunmur.
- **Staging indeksləşmir**: `APP_ENV` produksiya olmayanda hər cavab
  `X-Robots-Tag: noindex, nofollow` alır.
- **`DEPLOY.md`**: deploy ardıcıllığı (nüsxə → pull → composer → migrate → build → keş),
  deploydan sonra yoxlama və geri qaytarma addımları.

Subdomen, DNS, ayrıca baza və `.env` kimi server səviyyəli addımlar təsdiq gözləyir.

**Testlər:** 333 test / 1584 assertion (yeni `DeploymentToolsTest`: 4 test).

### 2026-09-21 — Şagird statistikası (P2 Mərhələ 7)

**Migration yoxdur** — bütün rəqəmlər mövcud dondurulmuş nəticələrdən (`attempt_sections`,
`attempt_answers`) hesablanır, ona görə sual bankı sonradan dəyişsə də köhnə statistika dəyişmir.

**Yeni səhifə: `/student/statistics`** (menyuda "Statistika"):

- Ümumi göstəricilər: tamamlanmış cəhd sayı, orta və ən yüksək nisbi bal, imtahanda keçən vaxt.
- Cəhdlərin zaman qrafiki (kitabxanasız SVG — yeni asılılıq əlavə olunmadı).
- Fənn üzrə cədvəl: cəhd sayı, orta, ən yüksək, son nəticə və son iki cəhdin fərqi.
- Zəif mövzular: ən azı 3 sual cavablandırılmış və düzgünlüyü 60%-dən aşağı olan mövzular;
  yanında ən güclü 5 mövzu.
- Giriş hüququ olan imtahanlar: mənbə (ödəniş/admin/pulsuz), tarix və status.

**Nəticə səhifəsi.** Eyni imtahanın əvvəlki cəhdi ilə müqayisə (fərq müsbət/mənfi göstərilir),
cəhdlərin qrafiki və bu cəhdin mövzu üzrə bölgüsü əlavə olundu.

**Qeyd:** yoxlanmamış yazılı cavab (`grade_ratio` boş) nə düz, nə səhv sayılır — admin
yoxlayandan sonra statistikaya avtomatik düşür.

**Testlər:** 329 test / 1573 assertion (yeni `StudentStatisticsTest`: 14 test).

### 2026-09-21 — SEO: rusca ünvanlar, JSON-LD və sitemap (P2 Mərhələ 6)

**Migration:** `categories.ru_path` (unikal, boş ola bilər).

**Rusca ünvanlar.** Rus səhifələri artıq tərcümə olunmuş ASCII ünvanla açılır:
`/ru/abituriyent/1-ci-qrup` → `/ru/abiturient/1-ya-gruppa`. Köhnə ünvan 301 ilə yenisinə
yönləndirilir, Azərbaycan ünvanları isə toxunulmadan qalır (`mekteb`, `miq`,
`suruculuk-imtahani`). Ünvanı olmayan düyün Azərbaycan yolu ilə açılır — tərcümə gözləmir.

**canonical/hreflang.** Səhifə öz dil variantlarını verə bilir (prefiks dəyişməklə alınmır),
`seo` prop-u və Blade ehtiyat teqləri eyni mənbədən oxuyur.

**JSON-LD.** Kateqoriya səhifələrində BreadcrumbList serverdə yazılır — axtarış robotu
JavaScript icra etmədən yol zəncirini görür.

**sitemap.xml.** Ana səhifə, qaydalar, bütün aktiv kateqoriyalar və imtahanı olan mövzu
sınağı/rüb səhifələri; hər bənd hər iki dildə, `xhtml:link` ilə qarşılıqlı hreflang.
Nəticə 1 saat keşlənir, admin kateqoriyanı dəyişəndə keş təmizlənir.

**robots.txt.** Panel ünvanları (`/admin`, `/student`, `/teacher`, `/profile`, `/payments`)
bağlandı, sitemap ünvanı göstərildi.

**Admin.** Kateqoriya formasında "Rus dili" bloku: rusca ünvan, ad, qısa təsvir, title,
meta description, H1 və giriş mətni. Valideynin rusca ünvanı dəyişəndə alt ağac da yenilənir.

**Testlər:** 315 test / 1503 assertion (yeni `SeoTest`: 16 test).

### 2026-09-21 — Rus sektoru (P2 Mərhələ 5)

**Migration:** `users.sector`, `questions.language` + `translation_group_id`, `exams.sector`,
`categories.ru_enabled`, `category_subject.sector` (unikal açar `category_id + subject_id +
sector` oldu). Migration addım-addım şərtlidir: yarımçıq qalmış icra təkrar işlədilə bilər.

**Sektor interfeys dilindən ayrıdır.** Rus sektorunda oxuyan şagird interfeysi Azərbaycanca
saxlaya bilər. Kimin nə görməsi:

- **Qonaq:** URL dili defoltdur (`/ru/...` → rus sektoru), kataloqdakı keçidlə dəyişir, seçim
  sessiyada qalır və qeydiyyat formasında öncədən seçilmiş gəlir.
- **Daxil olmuş istifadəçi:** yalnız `users.sector` (URL dili təsir etmir). Sektor profildən
  dəyişir; alınmış imtahanlara giriş dəyişmir, çünki giriş konkret imtahana bağlıdır.

**İmtahanın sektoru ilə sualın dili uyğun olmalıdır.** Rus sektoru imtahanına Azərbaycan dilində
sual bağlamaq — əl ilə də, bankdan da — validasiya ilə bloklanır. İmtahanda yaradılan sual
avtomatik imtahanın dilində yaranır, import və generasiya da yalnız həmin dildən götürür.
Sual bağlandıqdan sonra imtahanın sektoru dəyişdirilmir.

**Ana dili sektora görə dəyişir.** `category_subject.sector` sayəsində I mərhələdə az sektorunda
Azərbaycan dili, ru sektorunda Rus dili ana dili fənnidir (pivotda `sector = null` olan fənn hər
iki sektora aiddir). Orta məktəb altındakı "Azərbaycan dili (dövlət dili kimi)" aktivləşdirildi.

**Kataloq.** `categories.ru_enabled` bayrağı ağacda aşağı ötürülür; məzmun hazır olmayan
bölmədə (məs. sürücülük) ru keçidi göstərilmir. Kataloq və şagird kabineti yalnız cari sektorun
imtahanlarını siyahılayır, başqa sektorun imtahanı 404 qaytarır.

**Admin.** Sual bankında dil sütunu və filtri (imtahandan gələndə siyahı imtahanın dilinə
bərkidilir), imtahan yaratma/redaktə formasında və "bankdan imtahan yarat" səhifəsində sektor
seçimi; generasiyada fənn siyahısı seçilmiş sektora görə dəyişir.

**Açıq qalan:** III qrup üçün ru sektorunun ana dili (Rus dili) maksimal balı DİM sənədindən
təsdiqlənməyib, ona görə rəqəm əlavə edilmədi — hazırda III qrupun fənn siyahısı hər iki sektorda
eynidir (ROADMAP-da qeyd olunub).

**Testlər:** 297 test / 1382 assertion (yeni `SectorTest`: 19 test).

### 2026-09-21 — Bankdan imtahan generasiyası və rüb üzrə mövzu sınağı (P2 Mərhələ 4)

**Migration:** `topics.parent_id` (mövzu qrupları), `subjects.is_language`,
`exams.kind/quarter/is_cumulative`; ayrıca "Tarix" birləşdirmə migration-ı.

**Tarix bir fəndir.** DİM-də "Tarix" tək fəndir, bazada isə iki ayrı fənn vardı. İndi "Tarix"
fənni var, "Azərbaycan tarixi" və "Ümumi tarix" onun **mövzu qruplarıdır** (`topics.parent_id`).
25 sual, bal matrisi, imtahan və bölmə istinadları köçürüldü; geri qaytarma mümkündür
(hər sual mövzu qrupuna görə köhnə fənninə qayıdır). Nəticədə imtahanda bir "Tarix" bölməsi və
bir maksimal bal olur.

**Bankdan imtahan yarat.** Admin kateqoriyanı (qrup/altqrup), rübü (kumulyativ və ya yox) və hər
fənn üçün sual sayını seçir; sistem bankdan uyğun mövzuların suallarını təsadüfi seçib bölmələrə
yığır.

- **Variantlar arasında suallar təkrarlanmır:** 3 variant × 12 sual üçün bankda 36 unikal sual
  olmalıdır. Şagird A və B variantlarını alsa, eyni sualı görməz.
- **Bank çatmırsa heç nə yaradılmır:** hansı fəndə neçə sual çatmadığı göstərilir
  ("Riyaziyyat: 6 sual istənilib, bankda 4 var — 2 çatmır").
- **Xarici dil tək seçimdir** (radio): bir imtahana yalnız bir dil düşür, hər dil üçün ayrıca
  imtahan yaradılır.
- Nəticə **qaralamadır**: admin hər sualı "Əvəz et" ilə eyni hovuzdan başqası ilə dəyişə bilər,
  sonra dərc edir. **Dərc olunmuş imtahanın sualları dəyişdirilmir.**

**Şagird axını:** qrup → mövzu sınağı → rüb. Rüblər üçün ayrıca kateqoriya sətri yaradılmır —
`CategoryController` yolun sonundakı `/movzu-sinagi` və `/movzu-sinagi/{n}-ci-rub` seqmentlərini
özü emal edir. Hər iki dil dəstəklənir.

**Düzəldilən səhv:** kateqoriya səhifələrində **canonical və hreflang itmişdi** — Mərhələ 1-də
controller `seo` adlı prop göndərirdi və paylaşılan canonical prop-unu üzərinə yazırdı. Prop
`meta`-ya adlandırıldı, regres testi əlavə olundu. (Canlıda yoxlanıldı: `/abituriyent` indi
canonical və hər iki hreflang ilə gəlir.)

**Nəticə səhifəsi:** ümumi göstərici artıq "nisbi bal" adlanmır — `225.00 / 400 (56.3%)`.
NB yalnız fənn cədvəlində qalır.

**Testlər:** `ExamGenerationTest` (11), `TopicTrialFlowTest` (9), canonical regresi.
Cəmi 269 test / 1235 assertion.

### 2026-09-21 — Çoxfənli imtahanlar (P2 Mərhələ 3, bölmələr)

**Migration:** `exam_sections` (exam, fənn, başlıq, sual sayı, maksimal bal, sıra),
`exam_question.section_id` (NOT NULL), `attempt_questions.section_id`, `attempt_sections`.

**Bir kod yolu.** Mövcud tək-fənli imtahanların hər birinə migration ilə bir bölmə yaradıldı və
`section_id` NOT NULL edildi. Beləliklə bal hesablaması, cəhd səhifəsi və nəticə səhifəsi hər
imtahan üçün eyni məntiqlə işləyir — "bölməsiz imtahan" rejimi yoxdur.

**Bal.** Hər bölmə öz fənninin maksimal balı ilə ayrıca hesablanır (mövcud DİM düsturu dəyişmir,
sadəcə bölmə sayı qədər çağırılır), ümumi bal onların cəmidir. Ümumi maksimum bölmələrin
`max_score` cəmindən gəlir — sabit rəqəm yazılmır, çünki I mərhələ, buraxılış və dövlət qulluğu
imtahanlarında maksimum fərqlidir.

**Dondurma.** Bölmə nəticəsi `attempt_sections`-a yazılır: `max_score`, nisbi bal (NB), fənn balı,
düzgün/səhv/boş sayı və fənn adı surəti. Qrupun bal matrisi sonradan dəyişsə də köhnə nəticələr
dəyişmir (test bunu yoxlayır). Bölmə silinsə sətir qalır.

**Admin:** imtahan səhifəsi bölmələr üzrə quruldu — bölmə əlavə etmə (fənn, sual sayı, maks. bal),
boş bölmənin silinməsi (sonuncu bölmə və sualı olan bölmə silinmir), hər bölmə üçün ayrıca
"+ Sual" və "Bankdan" düymələri. Sual sıralaması bölmə daxilindədir.

**Şagird:** imtahan səhifəsində fənn tabları (çoxfənli imtahanda), nəticə səhifəsində fənn-fənn
cədvəl (düzgün/səhv/boş, nisbi bal, fənn balı / maksimum).

**Data:** 6 imtahan → 6 bölmə, 57 sual bağlantısı köçürüldü, bölməsiz sətir qalmadı.

**Testlər:** `MultiSubjectScoringTest` (5), `AdminExamSectionTest` (8). Cəmi 248 test /
1076 assertion.

**Qalır:** `exam_templates` (şablondan imtahan generasiyası, sabit variantlar A/B/C) — Mərhələ 3-ün
ikinci hissəsi.

### 2026-09-21 — Sual bankı (P2 Mərhələ 2)

**Migration (3 fayl):** `topics` cədvəli; `questions`-a `subject_id`, `topic_id`, `difficulty`,
`source`; `exam_question` pivotu; `attempt_questions` (cəhdin dondurulmuş sual siyahısı).
`questions.exam_id` və `questions.order` silindi.

**Struktur dəyişikliyi.** Əvvəl sual bir imtahana aid idi və təkrar istifadə oluna bilmirdi. İndi
sual fənnə (və istəyə bağlı mövzuya) aiddir, imtahanla əlaqə pivotdadır, sıra da orada saxlanılır.
Mövcud 57 sual köçürüldü: hər sual öz imtahanının fənnini aldı, 57 pivot bağlantısı quruldu,
279 variant toxunulmadı. Migration geri qaytarıla bilir (bir sual bir neçə imtahandadırsa, geri
qaytarmada yalnız birinci bağlantı qalır — fayl şərhində yazılıb).

**Davranış dəyişikliyi:** imtahan silinəndə sualları artıq silinmir, yalnız bağlantı kəsilir.
İmtahan səhifəsindəki "Sil" düyməsi "Ayır"a çevrildi.

**Cəhdin sual siyahısı dondurulur.** Suallar paylaşıldığı üçün imtahanın dəsti sonradan dəyişə
bilər. Cəhd başlayanda həmin andakı suallar `attempt_questions`-a yazılır; cəhd səhifəsi, bal
hesablaması, cavabsız sayı və nəticə səhifəsi imtahanın cari suallarından yox, bu siyahıdan işləyir.
`saveAnswer` da sualın bu siyahıda olduğunu yoxlayır.

**Cəhdlərdə işlənmiş sual:**
- bankdan **silinmir** (aydın mesajla dayandırılır), yalnız imtahandan ayrıla bilər;
- redaktə formasında "bu sual N cəhddə istifadə olunub" xəbərdarlığı görünür;
- "Kopyala və imtahanda əvəzlə" düyməsi sualın kopyasını yaradır və imtahanda onunla əvəzləyir —
  köhnə nəticələr orijinala istinad etməkdə davam edir.

**Admin:** sual bankı səhifəsi (fənn, mövzu, tip, çətinlik üzrə filtr, mətn axtarışı, hər sualın
neçə imtahanda və neçə cəhddə işləndiyi), imtahan səhifəsindən "Bankdan sual əlavə et" axını,
mövzu CRUD (rüb 1–4, sürücülük kimi fənlərdə boş qalır). Sual formasına mövzu, çətinlik və mənbə
sahələri əlavə olundu.

**Import:** şablona `movzu` və `cetinlik` sütunları əlavə edildi; mövzu adı fənnin mövzuları ilə
tutuşdurulur, tapılmasa sətir xəta verir.

**Testlər:** `QuestionBankTest` (12 — dondurulma zəmanətləri daxil), `AdminQuestionBankTest` (8).
Cəmi 224 test / 937 assertion.

### 2026-09-21 — Kateqoriya iyerarxiyası (P2 Mərhələ 1)

**Migration:** `categories` (parent_id, group_id, slug, path, SEO sahələri, translations),
`category_subject` pivotu, `exams.category_id`.

**Struktur qərarları:**
- `groups` cədvəli bal hesablaması üçün ayrıca qalır və ağacda təkrarlanmır: abituriyent qrup
  düyünləri `categories.group_id` ilə mövcud qruplara bağlanır (10 düyün).
- `category_subject.max_score` nullable — qrupa bağlı kateqoriyalarda bal `subject_group_scores`-dan
  gəlir, iki mənbə yaranmır. Dövlət qulluğu kimi qrupsuz kateqoriyalarda bal pivotdadır.
- `path` ayrıca sütundur: MİQ ağacda "Müəllimlər"in altındadır, URL-i isə `/miq` olaraq qalır.
  Bütün mövcud ünvanlar (`mekteb`, `abituriyent`, `magistratura`, `dovlet-qullugu`, `miq`,
  `suruculuk-imtahani`) qorundu.
- İmtahan ən dəqiq düyünə bağlanır; kateqoriya səhifəsi öz imtahanları ilə yanaşı bütün alt
  düyünlərin imtahanlarını göstərir. İmtahan saxlananda kateqoriyanın qrupu varsa `group_id`
  ondan götürülür.

**Seeder:** 54 kateqoriya — Orta məktəb, Abituriyent (I mərhələ, I–V qrup, RK/Rİ və DT/TC
altqrupları, Kollec), Magistratura, Dövlət qulluğu, Müəllimlər, Sürücülük, Digər (deaktiv).
`path` üzrə idempotent; təkrar işlədiləndə adminin deaktiv etdiyi kateqoriya geri açılmır.
Dövlət qulluğu üçün iki yeni fənn əlavə edildi: Qanunvericilik və Məntiq.

**Route:** hardcoded 6 kateqoriya route-u silindi, yerinə faylın sonunda `/{path}` catch-all.
Dil prefiksli variant ondan əvvəl qeydiyyatdan keçir — əks halda `/{path}` `ru/abituriyent`
ünvanını da udurdu. `RouteRegistrationTest` bütün mövcud route-ların yerində qaldığını yoxlayır.

**Frontend:** `resources/js/data/categories.js` və `lang/{az,ru}/categories.php` silindi, adlar
DB-dədir. Kök kateqoriyalar paylaşılan Inertia props-u ilə gəlir. Kateqoriya səhifəsi
placeholder-dən real kataloqa çevrildi: breadcrumb, alt bölmələr, fənlər və maksimal ballar,
satışdakı imtahanlar. Rusca adlar `translations` JSON sütununda — `/ru` səhifələri əvvəlki kimi
tərcümə olunur, tərcüməsi olmayan yeni düyünlər Azərbaycan adı ilə görünür.

**Admin:** kateqoriya CRUD (ağac görünüşü, SEO sahələri, aktiv/deaktiv). Slug dəyişəndə alt ağacın
bütün yolları yenilənir; kateqoriya öz alt ağacına köçürülə bilmir; uşağı və ya imtahanı olan
kateqoriya silinmir.

**Data:** mövcud 6 imtahan `/abituriyent/1-ci-qrup` düyününə bağlandı. İmtahan #1 pulsuz işarələndi.

**Testlər:** `CategoryTest` (22), `RouteRegistrationTest` (23). Cəmi 202 test / 775 assertion.

### 2026-09-21 — Asılılıq zəiflikləri, qalan P1 işləri və vizyon üzrə ROADMAP

**Təhlükəsizlik yeniləmələri.** `composer audit` 41 → 0, `npm audit` 14 → 0. Paket-paket
yeniləndi, hər addımdan sonra bütün testlər keçdi; major versiya dəyişikliyi lazım olmadı.
Laravel v12.47 → v12.69, vite 7.3.6, postcss 8.5.28, axios 1.20.0.

**Cəhdin həyat dövrü testləri** (`AttemptLifecycleTest`): vaxtı keçmiş cəhdin avtomatik bitməsi,
vaxt bitəndən sonra yeni cəhd, başqasının cəhdinə baxmaq/cavab yazmaq/bitirmək/nəticəsini görmək
üçün 403, bitmiş cəhdə cavab yazıla bilməməsi.

**Admin imtahan siyahısının filtrləri.** Səhifə controller-in gözlədiyi parametrləri göndərmirdi
(`subject`/`status` əvəzinə `subject_id`/`group_id`/`status`), ona görə fənn filtri heç vaxt
işləmirdi; controller isə `active`/`inactive` statuslarını emal etmirdi. Hər ikisi düzəldildi,
qrup filtri və "sıfırla" düyməsi əlavə olundu, səhifələmə filtrləri saxlayır.

**Qiymət validasiyası.** `is_free = false` olanda qiymət məcburidir və sıfırdan böyük olmalıdır.
Əvvəl ödənişli imtahan 0 AZN qiymətlə saxlanıla bilirdi: kataloqda "0 AZN" görünür və "Al" düyməsi
sıfır məbləğli ödəniş yaradırdı. Pulsuz işarələnəndə qiymət avtomatik sıfırlanır.

**ROADMAP.** Layihə vizyonu sənədi mövcud kodla tutuşduruldu; qalan işlər P2-də altı mərhələyə
bölündü (kateqoriya iyerarxiyası → çoxfənli imtahanlar → mövzular və rüb sınağı → rus sektoru →
SEO/sitemap → şagird statistikası), sonraya qalanlar P3-də.

**Testlər:** 157 test / 616 assertion.

### 2026-09-21 — DİM bal sistemi, qruplar və açıq sualların qiymətləndirilməsi

**Qrup strukturu.** `groups`-a `parent_id`, `code`, `stage`, `is_testable` əlavə olundu, `number`
nullable oldu. Altqruplar ayrıca cədvəl deyil, `parent_id` ilə qurulur — altqrupların balları
eynidir, altqrup yalnız fənn dəstini göstərir, ona görə ballar baş qrupa bağlanır.

| Qrup | Fənlər |
|------|--------|
| I (RK / Rİ) | Riyaziyyat, Fizika, Kimya / İnformatika |
| II | Riyaziyyat, Tarix, Coğrafiya |
| III (DT / TC) | Ana dili, Ədəbiyyat / Coğrafiya, Tarix |
| IV | Fizika, Kimya, Biologiya |
| V | Qabiliyyət — testi yoxdur |
| I mərhələ | Ana dili, Riyaziyyat, Xarici dil |

**Bal matrisi.** `subject_group_scores.score` → `max_score`, `decimal(6,2)` (köhnə `decimal(4,2)`
150 balı saxlaya bilmirdi). Mənası "bir sualın balı"ndan "fənnin qrupdakı maksimal balı"na dəyişdi.
DİM-in "Tarix" fənni bazada iki fənnə bölündüyü üçün hər ikisinə eyni bal verilir; "Xarici dil"
mövcud dörd dilə şamil olunur.

**DİM düsturu** (`App\Services\Scoring\DimBachelorStrategy`):

```
NBq = max(0, Dq − Yq × penalty)
NBa = Dkod + 2 × Σ(yazılı cavabların şkala qiymətləri)
NB  = (NBq + NBa) × 100 / (Nq + Nkod + 2 × Nyazılı)      → 0.1-ə yuvarlaqlaşdırılır
Fənn balı = NB × max_score / 100
```

`penalty` imtahanın mərhələsinə görədir (`config/scoring.php`): II mərhələ (I–IV qrup) 0.25,
I mərhələ və buraxılış 0. Unit testlər dörd tam formatda (II mərhələ 22+5+2×3=33, xarici dil
23+2×7=37, ana dili 20+2×10=40, riyaziyyat 13+5+2×7=32) tam cavabın məhz **100** bal verdiyini
və qarışıq nümunələri yoxlayır. Qısa sınaq imtahanlarında eyni düstur proporsional işləyir.

**Sual tiplərinin yoxlanması:** `multiple_choice` — variant; `open_coded` — `AnswerNormalizer` ilə
avtomatik (ədədi müqayisə); `open_written` — admin şkala ilə (`0, 1/3, 1/2, 2/3, 1`) qiymətləndirir.
Yoxlanmamış yazılı cavab varsa cəhd `pending_review` olur, bal müvəqqətidir; hər qiymətdən sonra bal
yenidən hesablanır, hamısı yoxlananda cəhd `completed` olur. Şagird nəticədə "açıq suallar yoxlanılır"
xəbərdarlığını, həm 100 ballıq nisbi balı (NB), həm də çəkili fənn balını (məs. 150-dən) görür.

**Admin qiymətləndirmə növbəsi:** `/admin/grading` — yoxlanmalı cəhdlər, şagirdin cavabı, şkala
düymələri.

**Düzəldilən səhvlər:**
- Şagird imtahan səhifəsindəki açıq cavab sahəsi heç nəyə bağlı deyildi — yazılan cavab **ümumiyyətlə
  saxlanılmırdı**. İndi yazı bitəndən sonra avtomatik yadda saxlanılır.
- `saveAnswer` sualın həmin imtahana, variantın həmin suala aid olduğunu yoxlamırdı.
- `attempt()` hər sual üçün ayrıca sorğu göndərirdi (N+1) — cavablar bir dəfə yüklənir.
- `Exam::$appends['questions_count']` hər serializasiyada sorğu göndərirdi — silindi, `withCount`.
- İmtahan gedərkən düzgün variantın sızmadığı testlə təmin olundu.

**Data:** bütün test cəhdləri (18 cəhd, 116 cavab) silindi; 6 imtahan, 57 sual və 279 variant
saxlanıldı və I qrupa bağlandı. Admin hesabı toxunulmadı.

**Testlər:** `DimBachelorStrategyTest` (13), `AttemptScoringTest` (10). Cəmi 139 test / 499 assertion.

### 2026-09-21 — Alış, ödəniş və imtahana giriş hüququ

**Migration** (`2026_09_21_000002_create_payments_and_exam_accesses_tables`):

`payments` — **polimorfdur** (`purchasable_type`/`purchasable_id`): hazırda yalnız imtahan satılır,
fənn paketi, qrup paketi və abunə gələndə cədvəl dəyişməyəcək. `amount` `decimal(10,2)` və alış
anındakı qiyməti saxlayır — imtahanın qiyməti sonra dəyişsə də ödəniş tarixçəsi düz qalır.
`currency` defolt AZN. `provider_ref` unikaldır.

`exam_accesses` — `user_id`, `exam_id`, `source` (payment/manual/free), `payment_id`, `expires_at`,
`attempts_allowed` (null = limitsiz), `granted_by`, `note`, `revoked_at`, `unique(user_id, exam_id)`.

**Ödəniş qaydaları:**
- Status yalnız irəli gedir: `pending → paid | failed`, `paid → refunded`. İcazəsiz keçid xəta verir,
  yəni uğursuz ödəniş sonradan "uğurlu" callback ilə açıla bilmir.
- Callback **idempotentdir**: eyni cavab iki dəfə gəlsə status bir dəfə dəyişir və giriş iki dəfə
  yaradılmır.
- Ödənişin `paid` olması və girişin açılması **bir tranzaksiyadadır** (`lockForUpdate` ilə).
- İmza yoxlanışı interfeysdədir (`verifyCallback`); yanlış imzalı sorğu 403 alır.
- `payload`-da kart məlumatı saxlanılmır: həssas açarlar (`pan`, `cvv`, `exp_*` və s.) atılır,
  mətn içindəki tam kart nömrəsi son 4 rəqəmdən başqa maskalanır.
- `refunded` statusu girişi avtomatik ləğv edir.

**Giriş hüququ:**
- Pullu imtahan artıq girişsiz başladıla bilmir (əvvəl hər kəs pulsuz başlaya bilirdi).
- Təkrar alışda yeni sətir yaranmır — mövcud sətir yenilənir, müddət uzadılır
  (`PAYMENT_ACCESS_VALID_DAYS`, boş = müddətsiz).
- Ləğv sətri silmir (`revoked_at`): qeyd, kimin verdiyi və ödəniş bağlantısı audit üçün qalır.
- Admin əl ilə giriş verir (email və ya telefonla şagirdi tapır), qeydə köçürmənin qəbz nömrəsini
  yazır, lazım olsa ləğv edir. İstəyə bağlı: bitmə tarixi və cəhd limiti.

**FakePaymentGateway:** real bank əvəzinə öz sınaq səhifəmizə yönləndirir, orada "Uğurlu"/"Uğursuz"
seçilir və **real callback route-u** çağırılır — bütün axın indidən işləyir. Bank qoşulanda yalnız
yeni driver yazılacaq.

**Təhlükəsizlik:** `fake` provayderi produksiyada QADAĞANDIR — sınaq bank səhifəsinin route-u
ümumiyyətlə qeydiyyatdan keçmir, alış cəhdi xəta verir və "Al" düyməsi göstərilmir. Əks halda
kimsə saxta "Uğurlu" düyməsi ilə pulsuz giriş əldə edə bilərdi.

**Testlər:** `ExamPurchaseTest` (11), `AdminExamAccessTest` (14), `PaymentGatewayFactoryTest` (6).
Cəmi 116 test / 408 assertion keçir.

### 2026-09-21 — Excel/CSV ilə toplu sual importu

`maatwebsite/excel` paketi quraşdırıldı (lazım olan PHP genişlənmələri — zip, gd, xml, mbstring —
serverdə artıq mövcud idi, əlavə quraşdırma tələb olunmadı).

**Axın:** şablonu yüklə → doldur → faylı yüklə → **önizləmə** → təsdiq.

- **Şablon** (`.xlsx`) imtahana görə qurulur: variant sütunlarının sayı `options_per_question`-a
  uyğundur. İki vərəq var — "Suallar" (başlıqlar + üç nümunə sətir) və "İzah" (hər sütunun mənası,
  qaydalar).
- **Önizləmə** heç nə yazmır: hər sətir üçün tip, düzgün cavab və xətalar cədvəldə göstərilir.
  Yoxlanılan hallar: boş sual, tanınmayan tip, boş variant, imtahandakından artıq variant,
  variantlar arasında olmayan düzgün cavab, boş qısa cavab.
- **Yazma** bütöv bir tranzaksiyadadır: bir sətir belə xətalıdırsa heç bir sual yazılmır.
  İmport mövcud sualların ardınca sıralanır.
- Fayl önizləmə ilə təsdiq arasında `storage/app/private/question-imports` altında saxlanılır,
  uğurlu importdan sonra silinir; təsdiqlənməyən yükləmələr bir gündən sonra təmizlənir.
- Formatlar: `.xlsx` (əsas), `.xls`, UTF-8 `.csv`. Maksimum 10 MB.
- Şəkillər fayl ilə yüklənmir — sual yarandıqdan sonra redaktə səhifəsindən qoşulur.

**Testlər:** `AdminQuestionImportTest` (10 test). Cəmi 85 test / 302 assertion keçir.

### 2026-09-21 — Admin sual idarəsi və yeni sual tipləri

**Migration** (`2026_09_21_000001_add_question_types_and_accepted_answers`):
- `questions.type` `enum` → `string(32)`. Enum dəyişmək MariaDB-də cədvəli hər dəfə yenidən qurur;
  sətir sütunu yeni tip əlavə etməyi migrationsuz mümkün edir (validasiya `Question::TYPES`-dədir).
- Mövcud `open_ended` → `open_written` (produksiyada belə sual yox idi, data itkisi olmadı).
- `questions.accepted_answers` (JSON) — yalnız `open_coded` üçün.
- `exams.options_per_question` — 4 və ya 5, imtahan səviyyəsində.

**Sual tipləri:**

| Tip | Necə yoxlanır |
|-----|----------------|
| `multiple_choice` | Variant sayı imtahandakı ilə eyni olmalıdır, düz bir düzgün cavab |
| `open_coded` | `AnswerNormalizer` ilə avtomatik |
| `open_written` | Admin əl ilə qiymətləndirir |

**AnswerNormalizer** (`app/Support/AnswerNormalizer.php`): cavablar sətir kimi deyil, ƏDƏD kimi
müqayisə olunur. Admin `0,5` yazsa, şagirdin `0.5`, `.5`, `1/2`, `2/4` cavabları da qəbul olunur
(tolerantlıq 1e-9). Ədədə çevrilməyən cavablar normallaşdırılmış mətn kimi tutuşdurulur; Azərbaycan
əlifbasındakı `İ/i` və `I/ı` fərqi nəzərə alınır (`İKİ` = `iki`, `IKI` ≠ `iki`). `accepted_answers`
yalnız ədədi olmayan alternativlər üçündür.

**Backend:** `AdminQuestionController` (create/store/edit/update/destroy/move) və ortaq
`QuestionService`. Şəkil yükləmə məntiqi servisə köçürüldü — `TeacherQuestionController` də artıq
eyni servisi çağırır, iki fərqli implementasiya qalmadı. Redaktə zamanı yenidən yüklənməyən variant
şəkilləri itmir, istifadədən çıxan şəkillər isə diskdən silinir.

**Frontend:** `Components/Questions/QuestionForm.vue` — formula önizləməsi, tez-formula düymələri,
üç sual tipi, şəkil yükləmə bir komponentdə. `Admin/Questions/Create.vue` və `Edit.vue` onu işlədir.
`Admin/Exams/Show.vue`-da sual əlavə etmə, redaktə, silmə və yuxarı/aşağı sıralama düymələri,
formula ilə render.

**Testlər:** `AnswerNormalizerTest` (16 unit test), `AdminQuestionTest` (15 feature test).
Cəmi 75 test / 228 assertion keçir.

**Diqqət:** migration hələ produksiya bazasında işlədilməyib.

### 2026-09-21 — Admin imtahan yarada/redaktə edə bilir

**Problem:** `AdminExamController` `Admin/Exams/Create` və `Edit` səhifələrini render edirdi,
amma bu fayllar mövcud deyildi — `/admin/exams/create` və redaktə səhifəsi 500 verirdi.

**Həll:**
- `Admin/Exams/Create.vue`: fənn, qrup, başlıq, təsvir, müddət, pulsuz/qiymət. Müəllim seçimi
  yalnız müəllim modulu açıq olanda görünür. İmtahan qaralama kimi yaradılır.
- `Admin/Exams/Edit.vue`: başlıq, təsvir, müddət, pulsuz/qiymət, aktivlik. Fənn və qrup
  redaktə edilmir (imtahanda artıq suallar və cəhdlər ola bilər) — yalnız məlumat kimi göstərilir.
- `EXAM_OWNER_ID` təyin olunmayıbsa artıq 500 verilmir: səhifə açılanda xəbərdarlıq banneri,
  göndərişdə isə formada aydın validasiya mesajı görünür.
- Test infrastrukturu: `SubjectFactory`, `GroupFactory`, `ExamFactory` əlavə edildi,
  `Subject`/`Group`/`Exam` modellərinə `HasFactory` verildi.
- `AdminExamTest` (11 test): səhifələrin açılması, imtahanın yaradılması və sahibinin təyini,
  pullu imtahanın qiyməti, sahib konfiqurasiya olunmayanda aydın xəta, redaktə, validasiya,
  qonaq və şagird üçün giriş qadağası.

**Nəticə:** 44 test / 131 assertion, hamısı keçir. Frontend yenidən build edildi.

### 2026-09-21 — Giriş məlumatları repodan çıxarıldı

- Bu sənəddəki "Giriş Məlumatları" bölməsi (admin və müəllim hesabları) tamamilə silindi.
- `AdminUserSeeder` artıq parolu koddan deyil, `.env`-dən oxuyur: `ADMIN_SEED_EMAIL`,
  `ADMIN_SEED_PASSWORD`. İkisindən biri boşdursa seeder aydın mesajla dayanır.
  Dəyərlər `config/seeding.php` üzərindən oxunur — `php artisan config:cache` işlədiləndə
  Laravel `.env` faylını yükləmir və `env()` null qaytarardı.
- Seeder idempotentdir: hesab artıq varsa parol dəyişdirilmir, yalnız rol təmin edilir.
- `.env.example`-a açarlar dəyərsiz əlavə edildi.
- Repo tam skan edildi (parol / secret / token / açar bənzəri sətirlər, real email və domenlər):
  bu ikisindən başqa gizli məlumat tapılmadı.
- Testlər: `AdminUserSeederTest` — konfiqurasiya boşdursa xəta, düzgün konfiqurasiya ilə admin
  yaradılması, mövcud adminin parolunun qorunması.

**Diqqət:** bu dəyişiklik parolları git tarixçəsindən silmir — ilk commit-də qalırlar.
Həmin hesabların parolları dəyişdirilməlidir.

### 2026-09-21 — Test təməli bərpa edildi

**Problem:** `php artisan test` işləmirdi — 25 testdən 20-si sınırdı, qalanları da yanlış səbəbdən
"keçirdi". Bu, yeni funksionallıq üçün test yazmağı qeyri-mümkün edirdi.

**Səbəblər və həll:**

| Səbəb | Həll |
|-------|------|
| Serverdə PHP `pdo_sqlite` sürücüsü yox idi, `phpunit.xml` isə sqlite `:memory:` gözləyirdi | `php8.3-sqlite3` quraşdırıldı |
| `UserFactory` starter-kit-dən qalmışdı: `first_name`, `last_name`, `phone`, `locale` sahələrini doldurmurdu (NOT NULL pozuntusu) | Factory migrasiyalara uyğunlaşdırıldı; `admin()`, `teacher()`, `student()`, `unverified()`, `inactive()`, `withoutPhone()` state-ləri əlavə edildi |
| Testlər `web` guard ilə `actingAs()` edirdi, tətbiq isə `student` guard işlədir | Testlər `actingAs($user, 'student')`-ə keçirildi |
| Testlər köhnə `route('dashboard')` və `name` sahəsi ilə işləyirdi | Real axına uyğunlaşdırıldı (`student.dashboard`, `first_name`/`last_name`) |
| Test bazasında spatie rolları olmurdu, qeydiyyat `assignRole('student')`-də 500 verirdi | `Tests\TestCase` hər test bazası ilə birlikdə `RoleSeeder`-i yükləyir |

**Əlavə edilən testlər:** şagird olmayan hesabın vahid giriş formasından keçə bilməməsi, telefon
nömrəsinin serverdə `+994XXXXXXXXX` formatına gətirilməsi, şərtlərin qəbulunun məcburiliyi,
telefonun unikallığı.

**Nəticə:** 29 test / 75 assertion, hamısı keçir.

## 1. Saytın Yeni Məntiqi

Layihə **sınaq imtahanları satış platformasına** çevrildi:

- **Admin** — imtahanları özü yaradır (admin paneldən)
- **Tələbələr** — qeydiyyat keçib sınaq imtahanlarını həll edir
- **Müəllim tərəfi** — saytda görünmür, lakin `/teacher/login` ilə açıla bilir

---

## 2. Edilən İşlər

### 2.1 Müəllim Qeydiyyatı — Texniki Fənlər Əlavəsi

**Problem:** Qeydiyyat formasında yalnız humanitar fənlər görünürdü.

**Həll:**
- `TeacherRegisterController.php` — `->humanitarian()` filtrini silindi
- `Register.vue` — fənlər iki kateqoriyaya bölündü:
  - 🔵 **Humanitar fənlər** (Azərbaycan dili, Tarix, Ədəbiyyat, Dillər...)
  - 🟢 **Texniki fənlər** (Riyaziyyat, Fizika, Kimya, Biologiya, Coğrafiya, İnformatika)

---

### 2.2 Müəllim Verifikasiyasının Ləğvi

**Problem:** Qeydiyyatdan sonra müəllim admin təsdiqini gözləyirdi.

**Həll:**
- `TeacherRegisterController.php` → `is_verified: true` (default)
- Qeydiyyatdan sonra `awaiting-verification` yox, `dashboard`-a yönləndirilir
- Mövcud gözləmədəki bütün müəllimlər də verify edildi

---

### 2.3 Formula Dəstəyi (KaTeX)

**Məqsəd:** Riyaziyyat, Fizika, Kimya, Məntiq fənləri üçün sual və cavablarda formula yazmaq imkanı.

**Quraşdırılan:** `katex` npm paketi

**Yaradılan komponent:** `resources/js/Components/MathText.vue`
- `$...$` → inline formula (məs: `$x^2 + 3x = 0$`)
- `$$...$$` → blok formula
- Kimya üçün: `$\ce{H2SO4 + 2NaOH -> Na2SO4 + 2H2O}$`

**Formula dəstəyi əlavə edilən səhifələr:**

| Səhifə | Funksiya |
|--------|----------|
| `Teacher/Questions/Create.vue` | Yazarkən canlı önizləmə, tez-formula düymələri |
| `Teacher/Questions/Edit.vue` | Eyni |
| `Teacher/Exams/Show.vue` | Sual siyahısında render |
| `Student/Exams/Attempt.vue` | İmtahan zamanı render |
| `Student/Exams/Result.vue` | Nəticə səhifəsində render |

**Tez-formula düymələri (sual yazarkən):**
Kəsr, Kvadrat kök, Kvadrat, Alt indeks, Cəm (Σ), İnteqral, Pi (π), Sonsuzluq (∞), Ox (→), Kimyəvi reaksiya, CO₂, H₂SO₄

---

### 2.4 Ana Səhifə Dizaynı

**Məqsəd:** Saytı abituriyentlərə yönləndirilmiş, peşəkar görünümlü satış səhifəsinə çevirmək.

**Müəllim linkləri** ana səhifədən silindi (yalnız `/teacher/login` ilə açılır).

**Yeni dizayn elementləri:**
- **Fontlar:** Syne (başlıqlar) + Outfit (mətn) + DM Mono (rəqəmlər/kod)
- **Rəng paleti:** İsti krem fon `#F7F4EE`, tünd `#16130E`, qırmızı `#C0392B`
- **Hero:** İmtahan mockup kartı + animasiyalı floating teqlər
- **Marquee:** Fənn adlarının hərəkətli lenti
- **Bölmələr:** Necə işləyir → Fənlər → Üstünlüklər → CTA

---

### 2.5 AI Sual Yaratma (Geri Alındı)

Groq API (llama-3.3-70b) ilə sual yaratma funksionallığı əlavə edildi, lakin sual keyfiyyəti qeyri-kafi olduğu üçün **tamamilə silindi**.

Silinən fayllar:
- `app/Services/GrokService.php`
- `app/Http/Controllers/Teacher/AiQuestionController.php`

---

## 3. Fənn Siyahısı (DB)

| ID | Ad | Kateqoriya |
|----|----|------------|
| 1 | Azərbaycan dili | humanitarian |
| 2 | Azərbaycan tarixi | humanitarian |
| 3 | Ümumi tarix | humanitarian |
| 4 | Ədəbiyyat | humanitarian |
| 5 | İngilis dili | humanitarian |
| 6 | Rus dili | humanitarian |
| 7 | Fransız dili | humanitarian |
| 8 | Alman dili | humanitarian |
| 9 | Riyaziyyat | technical |
| 10 | Fizika | technical |
| 11 | Kimya | technical |
| 12 | Biologiya | technical |
| 13 | Coğrafiya | technical |
| 14 | İnformatika | technical |

---

## 4. Mühüm Fayl Yolları

```
app/
  Http/
    Controllers/
      Admin/          — Admin paneli
      Teacher/        — Müəllim tərəfi (gizli)
      Student/        — Tələbə tərəfi
    Middleware/
      EnsureTeacherIsVerified.php

resources/js/
  Components/
    MathText.vue      — Formula render komponenti
  Pages/
    Welcome.vue       — Ana səhifə (yenilənib)
    Teacher/
      Questions/
        Create.vue    — Formula dəstəkli sual yaratma
        Edit.vue      — Formula dəstəkli redaktə
      Exams/
        Show.vue      — Formula render
    Student/
      Exams/
        Attempt.vue   — İmtahan səhifəsi (formula)
        Result.vue    — Nəticə (formula)
```

---

## 5. Növbəti Addımlar (Planlaşdırılıb)

- [ ] Ödəniş sistemi (tələbələr imtahan alacaq)
- [ ] Tələbə dashboard-u (keçmiş imtahanlar, statistika)
- [ ] İmtahan kataloqu (fənnə, qiymətə görə filter)
- [ ] Admin paneldən imtahan yaratma (müəllim panelindən müstəqil)
