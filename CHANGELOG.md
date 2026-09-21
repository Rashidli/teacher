# Proyekt Dəyişiklikləri

## Ümumi Məlumat

**Sayt:** https://teacher.cvhazirla.az  
**Framework:** Laravel + Inertia.js + Vue.js  
**DB:** MySQL (`websites_teacher_exam_db`)

---

## Jurnal (yeni dəyişikliklər üstdə)

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
