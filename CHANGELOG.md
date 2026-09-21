# Proyekt Dəyişiklikləri

## Ümumi Məlumat

**Sayt:** https://teacher.cvhazirla.az  
**Framework:** Laravel + Inertia.js + Vue.js  
**DB:** MySQL (`websites_teacher_exam_db`)

---

## Jurnal (yeni dəyişikliklər üstdə)

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
