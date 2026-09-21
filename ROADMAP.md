# ROADMAP — tələbə tərəfi MVP

**Kontekst:** Laravel 12 + Inertia + Vue 3 + Tailwind. Müəllim modulu `FEATURE_TEACHERS=false` ilə
söndürülüb və söndürülü qalır.

**Məqsəd:** admin imtahan və sual daxil edir → tələbə imtahanı alır → işləyir → DİM formatında bal görür.

## İş qaydası

- Hər tapşırıq bitəndə burada `[x]` işarələnir.
- Hər tapşırıq ayrıca commit (mənalı mesajla) və GitHub-a push.
- `CHANGELOG.md`-yə nə dəyişdiyi yazılır — **heç vaxt parol, email, token, `.env` dəyəri yazılmır.**
- Böyük tapşırıqdan (migration, struktur dəyişikliyi) əvvəl plan təsdiqə göstərilir.
- Hər tapşırıqdan sonra əlaqəli feature test yazılır və `php artisan test` keçir.

---

## P-1: Test təməli (bloklayıcı)

Testlər olmadan digər tapşırıqların "test yaz və keçsin" şərti yerinə yetirilə bilməz.

- [x] PHP `pdo_sqlite` sürücüsü (serverdə quraşdırıldı, 21.09.2026)
- [x] `UserFactory` migrasiyalarla uyğunlaşdırılsın (`first_name`, `last_name`, `phone`, `locale`)
- [x] Mövcud 20 sınıq starter-kit testi işlək vəziyyətə gətirilsin

---

## P0: Təhlükəsizlik (ilk bu)

- [x] `CHANGELOG.md`-dən "Giriş Məlumatları" bölməsini tamamilə sil.
- [x] `AdminUserSeeder`: parolu hardcode etmə, `env('ADMIN_SEED_PASSWORD')`-dən oxu; boşdursa seeder
      xəta versin. `.env.example`-a açarı (dəyərsiz) əlavə et.
- [x] Repo-da başqa gizli məlumat olub-olmadığını yoxla (`grep -ri "password\|secret\|token"`),
      tapılanı `.env`-ə köçür.

## P0: Admin imtahan yarada bilsin (hazırda 500 verir)

- [x] `resources/js/Pages/Admin/Exams/Create.vue` və `Edit.vue` yoxdur, amma controller onları render
      edir. Yarat (mövcud admin dizaynına uyğun).
- [x] `EXAM_OWNER_ID` məntiqini yoxla: `.env`-də yoxdursa admin aydın mesaj görsün, 500 yox.

## P0: Admin sual idarəsi

- [x] Admin panelində imtahan daxilində sual CRUD: yaratma, redaktə, silmə, sıralama.
      `Teacher/Questions/Create.vue` və `Edit.vue`-dakı formula önizləməsini (MathText, tez-formula
      düymələri) təkrar istifadə et, ortaq komponentə çıxar.
- [x] Şəkil yükləmə (sual və variant şəkli), storage link.
- [x] Sual növləri:
      - `multiple_choice` — variant sayı 4 və ya 5, imtahan səviyyəsində seçilir
      - `open_coded` — qısa/rəqəm cavab, bir neçə qəbul olunan cavab (`0,5` = `0.5` = `1/2`)
      - `open_written` — həll yazılır, əl ilə qiymətləndirilir
      Mövcud `open_ended` tipini `open_written`-ə migration ilə çevir.
- [x] Excel/CSV ilə toplu sual importu (şablon faylı ilə).

## P0: Alış və giriş hüququ

- [x] `exam_accesses` cədvəli: `user_id`, `exam_id`, `source` (payment/manual/free),
      `payment_id` (nullable), `expires_at` (nullable), timestamps; unique(user_id, exam_id).
- [x] `StudentExamController@start`: imtahan `is_free` deyilsə və tələbənin aktiv girişi yoxdursa,
      imtahan səhifəsinə "Al" mesajı ilə qaytar. Hazırda pullu imtahanı hər kəs pulsuz başlada bilir.
- [x] Admin paneldə tələbəyə əl ilə giriş vermək (ilk satışlar üçün).
- [x] `PaymentGateway` interface + `payments` cədvəli (status: pending/paid/failed/refunded, amount,
      provider, provider_ref, payload json). Konkret bank inteqrasiyası sonra; indi yalnız interface
      və callback route skeleti.
- [x] İmtahan kataloqunda qiymət, "Alınıb" / "Pulsuz" / "Al" statusu.

## P0: Qruplar və bal sistemi (DİM-ə uyğun)

- [x] Qrupları düzəlt (migration + seeder yenilə, mövcud datanı qoru):
      - I qrup: RK altqrupu (Riyaziyyat, Fizika, Kimya), Rİ altqrupu (Riyaziyyat, Fizika, İnformatika)
      - II qrup: Riyaziyyat, Tarix, Coğrafiya
      - III qrup: DT altqrupu (Ana dili, Ədəbiyyat, Tarix), TC altqrupu (Ana dili, Coğrafiya, Tarix)
      - IV qrup: Fizika, Kimya, Biologiya
      - V qrup: qabiliyyət (testsiz, yalnız məlumat)
      Altqrup üçün `groups.parent_id` və ya ayrıca `subgroups` cədvəli — hansı sadədirsə.
- [x] `subject_group_scores` cədvəlinin mənası "bir sualın balı"dan "fənnin qrupdakı maksimal balı"na
      dəyişsin:
      - I qrup: Riyaziyyat 150, Fizika 150, Kimya/İnformatika 100
      - II qrup: Riyaziyyat 150, Tarix 100, Coğrafiya 150
      - III qrup: Ana dili 150, Ədəbiyyat/Coğrafiya 100, Tarix 150
      - IV qrup: Fizika 100, Kimya 150, Biologiya 150
      - I mərhələ (ayrıca "qrup"): Ana dili 100, Riyaziyyat 100, Xarici dil 100
- [x] Bal hesablamasını `finishAttempt`-dən `App\Services\Scoring\` altına çıxar (Strategy pattern:
      `ScoringStrategy` interface, `DimBachelorStrategy`, sonra digərləri).
- [x] Dəqiq DİM düsturu (yanlış cavabın cəriməsi, xam balın fənn maksimumuna çevrilməsi, açıq sualların
      çəkisi) məlum deyil: `config/scoring.php`-də konfiqurasiya et, dəyərləri `TODO` şərhi ilə qoy.
      **Uydurma rəqəm yazma.**
- [x] `open_coded` suallar avtomatik yoxlansın. `open_written` suallar üçün cəhd statusu
      `pending_review` olsun, admin paneldə qiymətləndirmə növbəsi (şkala config-dən, defolt
      `0, 1/3, 1/2, 2/3, 1`), qiymətləndirmədən sonra bal yenidən hesablansın. Tələbə nəticədə
      "açıq suallar yoxlanılır" görsün.

---

## P1: Təhlükəsizlik və keyfiyyət

- [x] `composer audit` zəiflikləri: 41 zəiflik (13 high, 24 medium, 4 low) bağlandı, indi
      `No security vulnerability advisories found`. Paket-paket yeniləndi, hər addımdan sonra
      testlər keçdi. Major versiya dəyişikliyi lazım olmadı.
- [x] `npm audit` zəiflikləri: 14 zəiflik (2 critical, 8 high, 3 moderate, 1 low) bağlandı,
      indi `found 0 vulnerabilities`. Major versiya dəyişikliyi lazım olmadı.
- [x] `Admin/Exams/Index.vue` filtrləri düzəldildi: səhifə indi `subject_id`/`group_id`/`status`
      göndərir, seçimlər səhifə yenilənəndə qalır, qrup filtri və "sıfırla" düyməsi əlavə edildi;
      controller `active`/`inactive` statuslarını da emal edir, səhifələmə filtrləri saxlayır.
- [x] `saveAnswer`: `question_id` həmin imtahana, `selected_option_id` həmin suala aid olmalıdır
      (hazırda yoxlanmır).
- [x] `Exam::$appends = ['questions_count']` accessor-u hər serializasiyada sorğu göndərir: sil,
      `withCount` istifadə et.
- [x] `attempt()` və `finishAttempt()`-də N+1: cavabları bir dəfə yüklə, `keyBy('question_id')`.
- [x] Nəticədə düzgün cavabları yalnız imtahan bitəndən sonra göndər (attempt səhifəsinə `is_correct`
      sızmasın; hazırda sızmır, bunu testlə təmin et).
- [x] Feature testlər: pulsuz imtahan axını, pullu imtahan girişsiz bloklanır, giriş verildikdən sonra
      açılır, vaxt bitəndə avtomatik bitir, başqasının cəhdinə giriş 403, bal hesablaması.

---

## Gələcək qeydlər

- Produksiyada `composer install --no-dev --optimize-autoloader` — bax **P2.5: Staging mühiti**.

- Bal düsturu dəyişsə, mövcud cəhdləri yenidən hesablayan `attempts:rescore` artisan komandası
  lazım olacaq (`--dry-run` ilə köhnə və yeni balları yan-yana göstərsin). Hazırda bazada cəhd
  yoxdur, ona görə yazılmayıb.

## P2: Platforma vizyonu — mərhələlərlə

Mənbə: layihə vizyonu sənədi. Aşağıdakılar sənəddə olub, kodda **hələ olmayan** hissələrdir.
Bal hesablaması (DİM düsturu, ScoringStrategy, config/scoring.php) artıq həll olunub — toxunulmur.
Mövcud URL slug-ları (`mekteb`, `miq`, `suruculuk-imtahani`) saxlanılır.

### Mərhələ 1 — Kateqoriya iyerarxiyası ✅ (21.09.2026)

- [x] `categories` cədvəli: `parent_id` (sonsuz dərinlik), `group_id` (nullable — bal üçün mövcud
      `groups` cədvəlinə bağlantı), `slug`, `path` (URL üçün), `name`, `description`, `is_active`,
      `order` və SEO sahələri (`seo_title`, `seo_description`, `h1`, `intro`).
- [x] **`groups` cədvəli bal hesablaması üçün ayrıca qalır**, kateqoriya ağacında təkrarlanmır:
      abituriyent qrup düyünləri `categories.group_id` ilə mövcud qruplara bağlanır.
- [x] `category_subject` pivotu: `question_count`, `options_per_question`, `max_score` (nullable —
      qrupa bağlı kateqoriyalarda bal `subject_group_scores`-dan gəlir, təkrarlanmır).
- [x] Seeder ilə ağac: Orta məktəb, Abituriyent (I mərhələ, I–V qrup, altqruplar, Kollec),
      Magistratura, Dövlət qulluğu, Müəllimlər (MİQ, Sertifikasiya, Diaqnostik, Məktəbəqədər),
      Sürücülük (A, B, C, D, BE, CE, DE), Digər (deaktiv).
- [x] Mövcud slug-lar saxlanılır (`mekteb`, `miq`, `suruculuk-imtahani` …) — `path` sütunu
      iyerarxiyadan asılı olmadan sabit URL verir.
- [x] `routes/web.php`-dəki hardcoded slug siyahısı və `categories.js` DB-yə köçürülür,
      placeholder səhifələr real kataloqa çevrilir.
- [x] Admin: kateqoriya CRUD (ağac görünüşü, sıra, aktiv/deaktiv).

### Mərhələ 2 — Sual bankı (struktur dəyişikliyi) ✅ (21.09.2026)

Suallar hazırda birbaşa imtahana bağlıdır (`questions.exam_id`), ona görə təkrar istifadə oluna
bilmir. Çoxfənli imtahanlar və mövzu sınağı bunun üzərində qurulacaq, ona görə **əvvəl bu gəlir**.

- [x] `topics`: `subject_id`, `name`, `slug`, `quarter` (1–4), `order`.
- [x] `questions`-a: `subject_id`, `topic_id`, `difficulty` (sadə/orta/mürəkkəb), `source`.
- [x] `exam_question` pivotu (`exam_id`, `question_id`, `order`); `questions.exam_id` silinir.
- [x] Mövcud 57 sualın köçürülməsi: hər sual öz imtahanının fənninə bağlanır, pivot doldurulur.
      Migration geri qaytarıla bilən olmalıdır.
- [x] Admin: sual bankı səhifəsi (fənn/mövzu/çətinlik/tip üzrə filtr, axtarış), imtahana mövcud
      sual əlavə etmə, mövzu CRUD (sürücülük mövzuları da buradan).
- [x] Cəhdin sual siyahısı dondurulur (`attempt_questions`): imtahandan sual ayrılsa və ya kopya
      ilə əvəzlənsə belə köhnə nəticə səhifəsi dəyişmir. Cəhddə işlənmiş sual bankdan silinmir.
- [x] Excel importu mövzu və çətinlik sütunlarını da qəbul etsin.

### Mərhələ 3 — Çoxfənli imtahanlar (exam_sections) — bölmələr ✅ (21.09.2026), şablonlar qalır

Sual bankı üzərində qurulur: bölmə fənni göstərir, suallar bankdan seçilir.

- [x] `exam_sections`: `exam_id`, `subject_id`, `title`, `question_count`, `max_score`, `order`.
      Hər imtahanın ən azı bir bölməsi var (mövcud tək-fənli imtahanlar migration ilə köçürüldü) —
      ayrıca "bölməsiz" kod yolu yoxdur.
- [x] `attempt_sections`: bölmə üzrə nəticə hesablama anında dondurulur (`max_score` və NB daxil),
      bal matrisi sonra dəyişsə də köhnə nəticə eyni qalır.
- [x] Admin: bölmə əlavə etmə/silmə, sual və bankdan əlavə etmə bölmə üzrə.
- [x] Nəticə səhifəsində fənn-fənn bölgü; ümumi maksimum bölmələrin cəmindən hesablanır.
- [x] `exam_question` bölməyə bağlanır (`section_id`).
- [x] `ScoringStrategy` çoxfənli imtahanı dəstəkləsin: hər bölmə üzrə ayrıca bal, sonra ümumi bal
      (mövcud düstur fənn səviyyəsində işləyir, dəyişmir).
- [x] İmtahan interfeysində fənn tabları, nəticədə fənn-fənn bölgü.
      (`exam_templates` Mərhələ 4 ilə birləşdirildi — orada "Bankdan imtahan yarat" forması kimi.)

### Mərhələ 4 — Rüb üzrə mövzu sınağı və bankdan imtahan generasiyası

**Admin: "Bankdan imtahan yarat" forması**

- [ ] Formada seçilir: kateqoriya (qrup və ya altqrup), rüb (1–4) və kumulyativ olub-olmaması,
      hər fənn üçün sual sayı, müddət, variant sayı.
- [ ] Sistem bankdan uyğun mövzuların suallarını təsadüfi seçib bölmələrə yığır
      (qrupun fənləri → bölmələr, seçilmiş rübün mövzuları → suallar).
- [ ] Bankda kifayət qədər sual yoxdursa, **hansı fəndə neçə sual çatmadığı** göstərilir və
      imtahan yaradılmır.
- [ ] Yaradılan imtahan **qaralamadır**: admin nəticəyə baxır, istədiyi sualı əvəz edir
      (təsadüfi başqası ilə və ya bankdan seçməklə), yalnız bundan sonra dərc edir.
- [ ] Dərc olunmuş imtahan **sabit qalır** — suallar hər dəfə yenidən seçilmir.
- [ ] A/B/C variantları üçün ayrıca məntiq yoxdur: eyni formadan bir neçə imtahan yaradılır.

**Şagird axını**

- [ ] Qrup → mövzu sınağı → rüb → həmin rüb üçün dərc olunmuş imtahanlar.
- [ ] Mövzu testi (məşq): tək mövzu, taymersiz, bir hissəsi pulsuz.

### Mərhələ 5 — Rus sektoru

- [ ] `users.sector` (az/ru) — qeydiyyat və profildə; imtahan seçimində dəyişdirilə bilsin.
- [ ] `questions.language` (az/ru) + `translation_group_id` (eyni sualın iki dil versiyası).
- [ ] `category_subject.sector`: ana dili fənni sektora görə (az → Azərbaycan dili,
      ru → Rus dili) — I mərhələ, 9/11-ci sinif buraxılış, III qrup.
- [ ] Orta məktəb altında "Azərbaycan dili (dövlət dili kimi)" — yalnız ru sektorunda görünür.
- [ ] Kateqoriyada "ru sektoru aktivdir" bayrağı; məzmun hazır olmayanda ru seçimi gizlənir.
- [ ] İmtahan və məhsullar sektora bağlıdır (az alan ru-ya giriş almır).
- [ ] Admin: sual filtri sektora görə. `config/scoring.php`-də sektor üzrə override imkanı.

### Mərhələ 6 — SEO və sitemap

- [ ] Kateqoriya/fənn/mövzu səhifələri üçün DB-dən redaktə olunan title, meta description, H1, mətn.
- [ ] Breadcrumb + schema.org JSON-LD (BreadcrumbList, Quiz).
- [ ] `sitemap.xml` (hər iki dil), canonical (var) + rusca slug-lar (translit, ASCII).
- [ ] İç-içə URL-lər: `/abituriyent/1-ci-qrup/rk/movzu-sinagi/2-ci-rub` kimi.

### Mərhələ 7 — Şagird statistikası

- [ ] Fənn üzrə irəliləyiş, mövzu üzrə zəif yerlər analitikası.
- [ ] Əvvəlki cəhdlərlə müqayisə, nəticə səhifəsində qrafik.
- [ ] İstifadəçi kabineti: alınmış imtahanlar, keçmiş nəticələr.

---

## P2.5: Staging mühiti — PRODUKSİYAYA ÇIXMAZDAN ƏVVƏLKİ SON ADDIM

Hazırda bütün işlər birbaşa produksiya qovluğunda görülür: testlər, migration-lar və
`npm run build` canlı saytın üzərində işləyir. Satışa çıxmazdan əvvəl bu ayrılmalıdır.

- [ ] Ayrıca qovluq: `/home/websites/web/staging.teacher.cvhazirla.az/public_html`.
- [ ] Ayrıca subdomen: `staging.teacher.cvhazirla.az` (axtarış sistemlərindən bağlı —
      `noindex` + `robots.txt`, mümkünsə parol qorunması).
- [ ] Ayrıca baza: `websites_teacher_exam_staging` (produksiyadan surət, şagird məlumatları
      anonimləşdirilmiş).
- [ ] Ayrıca `.env`: `APP_ENV=staging`, `PAYMENT_DRIVER=fake` (staging-də sınaq ödənişi işləyir),
      `MAIL_MAILER=log`.
- [ ] İş qaydası: bütün dəyişikliklər staging-də edilir və orada yoxlanılır; produksiyaya
      **yalnız `git pull`** ilə çıxarılır (kod redaktəsi produksiyada aparılmır).
- [ ] Deploy addımları sənədləşdirilir: `git pull` → `composer install --no-dev
      --optimize-autoloader` → `php artisan migrate --force` → `npm ci && npm run build` →
      `php artisan config:cache route:cache view:cache`.
- [ ] Produksiyada `composer install --no-dev`: dev paketləri (phpunit, mockery, pint, sail)
      silinir — testlər staging-də işləyəcək.
- [ ] Deploydan əvvəl avtomatik baza backup-ı (hazırda əl ilə alınır).

## P3: Vizyondan qalan, sonraya saxlanılan

- [ ] Yeni sual növləri: uyğunluq (matching), mətn/situasiya əsaslı sual qrupu, esse.
- [ ] `open_written` cavabında şagirdin həll şəklini yükləməsi.
- [ ] Məhsul növləri: fənn paketi, qrup paketi, abunə (hazırda yalnız tək imtahan satılır).
- [ ] Rollar: rəyçi (sualı təsdiqləyən) və qiymətləndirici; sual təsdiq axını
      (müəllif → rəyçi → təsdiqlənmiş suallar imtahana düşür); sualda müəllif (gəlir bölgüsü üçün).
- [ ] Digər bal strategiyaları: `DimGraduation9/11`, `DimMaster`, `CivilService`, `TeacherMiq`,
      `DrivingTheory`. Dövlət qulluğunun bilinən qaydası: qapalı 1 bal, açıq 2 bal, yanlış/boş 0;
      müddət qapalı 1:30, açıq 2:00, esse 30 dəq.
- [ ] İmtahan interfeysində sual naviqasiya paneli (cavablanmış / boş / işarələnmiş), oflayn dayanıqlıq.
- [ ] Bank inteqrasiyası (hazırda FakePaymentGateway; produksiyada onlayn alış bağlıdır).
- [ ] Faza 4: repetitor modulu — şagird qrupları, imtahan təyini, kağız cavab kartlarının
      telefonla skan edilməsi (OMR), valideyn hesabatı.
