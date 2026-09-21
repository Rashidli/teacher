# Proyekt Dəyişiklikləri

## Ümumi Məlumat

**Sayt:** https://teacher.cvhazirla.az  
**Framework:** Laravel + Inertia.js + Vue.js  
**DB:** MySQL (`websites_teacher_exam_db`)

---

## 1. Giriş Məlumatları

### Admin
- **URL:** `/admin/login`
- **Email:** admin@teacher.cvhazirla.az
- **Parol:** [silindi]

### Müəllim (test)
- **URL:** `/teacher/login`
- **Email:** rashidliseymur@gmail.com
- **Parol:** [silindi]

---

## 2. Saytın Yeni Məntiqi

Layihə **sınaq imtahanları satış platformasına** çevrildi:

- **Admin** — imtahanları özü yaradır (admin paneldən)
- **Tələbələr** — qeydiyyat keçib sınaq imtahanlarını həll edir
- **Müəllim tərəfi** — saytda görünmür, lakin `/teacher/login` ilə açıla bilir

---

## 3. Edilən İşlər

### 3.1 Müəllim Qeydiyyatı — Texniki Fənlər Əlavəsi

**Problem:** Qeydiyyat formasında yalnız humanitar fənlər görünürdü.

**Həll:**
- `TeacherRegisterController.php` — `->humanitarian()` filtrini silindi
- `Register.vue` — fənlər iki kateqoriyaya bölündü:
  - 🔵 **Humanitar fənlər** (Azərbaycan dili, Tarix, Ədəbiyyat, Dillər...)
  - 🟢 **Texniki fənlər** (Riyaziyyat, Fizika, Kimya, Biologiya, Coğrafiya, İnformatika)

---

### 3.2 Müəllim Verifikasiyasının Ləğvi

**Problem:** Qeydiyyatdan sonra müəllim admin təsdiqini gözləyirdi.

**Həll:**
- `TeacherRegisterController.php` → `is_verified: true` (default)
- Qeydiyyatdan sonra `awaiting-verification` yox, `dashboard`-a yönləndirilir
- Mövcud gözləmədəki bütün müəllimlər də verify edildi

---

### 3.3 Formula Dəstəyi (KaTeX)

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

### 3.4 Ana Səhifə Dizaynı

**Məqsəd:** Saytı abituriyentlərə yönləndirilmiş, peşəkar görünümlü satış səhifəsinə çevirmək.

**Müəllim linkləri** ana səhifədən silindi (yalnız `/teacher/login` ilə açılır).

**Yeni dizayn elementləri:**
- **Fontlar:** Syne (başlıqlar) + Outfit (mətn) + DM Mono (rəqəmlər/kod)
- **Rəng paleti:** İsti krem fon `#F7F4EE`, tünd `#16130E`, qırmızı `#C0392B`
- **Hero:** İmtahan mockup kartı + animasiyalı floating teqlər
- **Marquee:** Fənn adlarının hərəkətli lenti
- **Bölmələr:** Necə işləyir → Fənlər → Üstünlüklər → CTA

---

### 3.5 AI Sual Yaratma (Geri Alındı)

Groq API (llama-3.3-70b) ilə sual yaratma funksionallığı əlavə edildi, lakin sual keyfiyyəti qeyri-kafi olduğu üçün **tamamilə silindi**.

Silinən fayllar:
- `app/Services/GrokService.php`
- `app/Http/Controllers/Teacher/AiQuestionController.php`

---

## 4. Fənn Siyahısı (DB)

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

## 5. Mühüm Fayl Yolları

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

## 6. Növbəti Addımlar (Planlaşdırılıb)

- [ ] Ödəniş sistemi (tələbələr imtahan alacaq)
- [ ] Tələbə dashboard-u (keçmiş imtahanlar, statistika)
- [ ] İmtahan kataloqu (fənnə, qiymətə görə filter)
- [ ] Admin paneldən imtahan yaratma (müəllim panelindən müstəqil)
