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

- [ ] `CHANGELOG.md`-dən "Giriş Məlumatları" bölməsini tamamilə sil.
- [ ] `AdminUserSeeder`: parolu hardcode etmə, `env('ADMIN_SEED_PASSWORD')`-dən oxu; boşdursa seeder
      xəta versin. `.env.example`-a açarı (dəyərsiz) əlavə et.
- [ ] Repo-da başqa gizli məlumat olub-olmadığını yoxla (`grep -ri "password\|secret\|token"`),
      tapılanı `.env`-ə köçür.

## P0: Admin imtahan yarada bilsin (hazırda 500 verir)

- [ ] `resources/js/Pages/Admin/Exams/Create.vue` və `Edit.vue` yoxdur, amma controller onları render
      edir. Yarat (mövcud admin dizaynına uyğun).
- [ ] `EXAM_OWNER_ID` məntiqini yoxla: `.env`-də yoxdursa admin aydın mesaj görsün, 500 yox.

## P0: Admin sual idarəsi

- [ ] Admin panelində imtahan daxilində sual CRUD: yaratma, redaktə, silmə, sıralama.
      `Teacher/Questions/Create.vue` və `Edit.vue`-dakı formula önizləməsini (MathText, tez-formula
      düymələri) təkrar istifadə et, ortaq komponentə çıxar.
- [ ] Şəkil yükləmə (sual və variant şəkli), storage link.
- [ ] Sual növləri:
      - `multiple_choice` — variant sayı 4 və ya 5, imtahan səviyyəsində seçilir
      - `open_coded` — qısa/rəqəm cavab, bir neçə qəbul olunan cavab (`0,5` = `0.5` = `1/2`)
      - `open_written` — həll yazılır, əl ilə qiymətləndirilir
      Mövcud `open_ended` tipini `open_written`-ə migration ilə çevir.
- [ ] Excel/CSV ilə toplu sual importu (şablon faylı ilə).

## P0: Alış və giriş hüququ

- [ ] `exam_accesses` cədvəli: `user_id`, `exam_id`, `source` (payment/manual/free),
      `payment_id` (nullable), `expires_at` (nullable), timestamps; unique(user_id, exam_id).
- [ ] `StudentExamController@start`: imtahan `is_free` deyilsə və tələbənin aktiv girişi yoxdursa,
      imtahan səhifəsinə "Al" mesajı ilə qaytar. Hazırda pullu imtahanı hər kəs pulsuz başlada bilir.
- [ ] Admin paneldə tələbəyə əl ilə giriş vermək (ilk satışlar üçün).
- [ ] `PaymentGateway` interface + `payments` cədvəli (status: pending/paid/failed/refunded, amount,
      provider, provider_ref, payload json). Konkret bank inteqrasiyası sonra; indi yalnız interface
      və callback route skeleti.
- [ ] İmtahan kataloqunda qiymət, "Alınıb" / "Pulsuz" / "Al" statusu.

## P0: Qruplar və bal sistemi (DİM-ə uyğun)

- [ ] Qrupları düzəlt (migration + seeder yenilə, mövcud datanı qoru):
      - I qrup: RK altqrupu (Riyaziyyat, Fizika, Kimya), Rİ altqrupu (Riyaziyyat, Fizika, İnformatika)
      - II qrup: Riyaziyyat, Tarix, Coğrafiya
      - III qrup: DT altqrupu (Ana dili, Ədəbiyyat, Tarix), TC altqrupu (Ana dili, Coğrafiya, Tarix)
      - IV qrup: Fizika, Kimya, Biologiya
      - V qrup: qabiliyyət (testsiz, yalnız məlumat)
      Altqrup üçün `groups.parent_id` və ya ayrıca `subgroups` cədvəli — hansı sadədirsə.
- [ ] `subject_group_scores` cədvəlinin mənası "bir sualın balı"dan "fənnin qrupdakı maksimal balı"na
      dəyişsin:
      - I qrup: Riyaziyyat 150, Fizika 150, Kimya/İnformatika 100
      - II qrup: Riyaziyyat 150, Tarix 100, Coğrafiya 150
      - III qrup: Ana dili 150, Ədəbiyyat/Coğrafiya 100, Tarix 150
      - IV qrup: Fizika 100, Kimya 150, Biologiya 150
      - I mərhələ (ayrıca "qrup"): Ana dili 100, Riyaziyyat 100, Xarici dil 100
- [ ] Bal hesablamasını `finishAttempt`-dən `App\Services\Scoring\` altına çıxar (Strategy pattern:
      `ScoringStrategy` interface, `DimBachelorStrategy`, sonra digərləri).
- [ ] Dəqiq DİM düsturu (yanlış cavabın cəriməsi, xam balın fənn maksimumuna çevrilməsi, açıq sualların
      çəkisi) məlum deyil: `config/scoring.php`-də konfiqurasiya et, dəyərləri `TODO` şərhi ilə qoy.
      **Uydurma rəqəm yazma.**
- [ ] `open_coded` suallar avtomatik yoxlansın. `open_written` suallar üçün cəhd statusu
      `pending_review` olsun, admin paneldə qiymətləndirmə növbəsi (şkala config-dən, defolt
      `0, 1/3, 1/2, 2/3, 1`), qiymətləndirmədən sonra bal yenidən hesablansın. Tələbə nəticədə
      "açıq suallar yoxlanılır" görsün.

---

## P1: Təhlükəsizlik və keyfiyyət

- [ ] `saveAnswer`: `question_id` həmin imtahana, `selected_option_id` həmin suala aid olmalıdır
      (hazırda yoxlanmır).
- [ ] `Exam::$appends = ['questions_count']` accessor-u hər serializasiyada sorğu göndərir: sil,
      `withCount` istifadə et.
- [ ] `attempt()` və `finishAttempt()`-də N+1: cavabları bir dəfə yüklə, `keyBy('question_id')`.
- [ ] Nəticədə düzgün cavabları yalnız imtahan bitəndən sonra göndər (attempt səhifəsinə `is_correct`
      sızmasın; hazırda sızmır, bunu testlə təmin et).
- [ ] Feature testlər: pulsuz imtahan axını, pullu imtahan girişsiz bloklanır, giriş verildikdən sonra
      açılır, vaxt bitəndə avtomatik bitir, başqasının cəhdinə giriş 403, bal hesablaması.

---

## P2: Satışdan sonra (hələ başlanmır)

- [ ] Çoxfənli imtahanlar: `exam_sections` (exam_id, subject_id, question_count, max_score, order).
      Ümumi sınaq və qrup üzrə mövzu sınağı bunun üzərində qurulacaq.
- [ ] Mövzular (`topics`: subject_id, name, quarter 1–4), suala `topic_id`; mövzu sınağı:
      qrup → rüb → qrupun bütün fənlərindən imtahan (kumulyativ və ya yox, seçim).
- [ ] Rus sektoru: istifadəçidə `sector` (az/ru), imtahanda `sector`, ana dili fənni sektora görə
      (Azərbaycan dili / Rus dili), ru sektoru üçün "Azərbaycan dili (dövlət dili kimi)".
- [ ] Kateqoriya iyerarxiyası DB-də (hazırda `routes/web.php`-də hardcoded slug-lar və
      `categories.js`): orta məktəb, abituriyent, magistratura, dövlət qulluğu, MİQ, sürücülük;
      hər kateqoriyaya imtahanlar bağlansın, placeholder səhifələr real kataloqa çevrilsin.
- [ ] SEO: sitemap.xml, kateqoriya/fənn səhifələri üçün meta, schema.org.
- [ ] Tələbə statistikası: fənn üzrə irəliləyiş, zəif mövzular.
