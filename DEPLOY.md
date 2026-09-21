# Deploy qaydası

Bu sənəd produksiyaya çıxarma addımlarını təsbit edir. **Hazırda kod birbaşa produksiya
qovluğunda yazılır** — staging mühiti qurulandan sonra (P2.5) qayda dəyişəcək:
dəyişiklik staging-də edilir, produksiyaya yalnız `git pull` ilə çıxarılır.

## Qovluqlar

| Mühit | Qovluq | Baza | URL |
|---|---|---|---|
| Produksiya | `/home/websites/web/teacher.cvhazirla.az/public_html` | `websites_teacher_exam_db` | https://teacher.cvhazirla.az |
| Staging (planlaşdırılır) | `/home/websites/web/staging.teacher.cvhazirla.az/public_html` | `websites_teacher_exam_staging` | https://staging.teacher.cvhazirla.az |

Ehtiyat nüsxələr `public_html`-dən **kənarda** saxlanılır:
`/home/websites/web/teacher.cvhazirla.az/backup_db_*.sql` (yalnız son 3 nüsxə;
fayl adındakı vaxt UTC-dir, silinmə isə fayl tarixinə görə aparılır).

## Deploy addımları (ardıcıllığı dəyişmə)

```bash
cd /home/websites/web/teacher.cvhazirla.az/public_html

php artisan db:backup                 # 1. Nüsxə (migration-dan ƏVVƏL)
git pull                              # 2. Kod
composer install --no-dev --optimize-autoloader   # 3. Asılılıqlar
php artisan migrate --force           # 4. Baza
npm ci && npm run build               # 5. Frontend
php artisan optimize:clear            # 6. Keşlər
```

Kateqoriya ağacı və ya bal matrisi dəyişibsə, əlavə olaraq:

```bash
php artisan db:seed --class=SubjectGroupScoreSeeder --force
php artisan db:seed --class=CategorySeeder --force
```

Seeder-lər idempotentdir (təkrar işlədilə bilər). `migrate:fresh` **işlədilmir**.

## Deploydan sonra yoxlama

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://teacher.cvhazirla.az/
curl -s -o /dev/null -w "%{http_code}\n" https://teacher.cvhazirla.az/abituriyent
curl -s -o /dev/null -w "%{http_code}\n" https://teacher.cvhazirla.az/ru/abiturient
curl -s https://teacher.cvhazirla.az/sitemap.xml | head -3
```

Brauzerdə: ana səhifə, bir kateqoriya səhifəsi, giriş və qeydiyyat formaları.

## Geri qaytarma

```bash
git log --oneline -5                  # hansı commit-ə qayıdırıq
git reset --hard <commit>
npm run build
# Baza dəyişibsə:
php artisan migrate:rollback --step=1
# və ya nüsxədən:
# mariadb -u <user> -p websites_teacher_exam_db < /home/websites/web/teacher.cvhazirla.az/backup_db_<tarix>.sql
```

## Staging qaydaları (qurulandan sonra)

- `.env`: `APP_ENV=staging`, `APP_DEBUG=false`, `PAYMENT_DRIVER=fake`, `MAIL_MAILER=log`.
- Axtarış sistemlərindən bağlıdır: `APP_ENV != production` olanda hər cavaba
  `X-Robots-Tag: noindex, nofollow` əlavə olunur (`PreventIndexingOutsideProduction`).
  Bundan əlavə staging-in öz `robots.txt`-i `Disallow: /` olmalıdır.
- Produksiyadan surət alandan sonra şəxsi məlumatlar təmizlənir:
  `php artisan staging:anonymize --password=<parol>` (admin hesabları toxunulmur;
  əmr produksiyada işləmir).
- Testlər staging-də işləyir: produksiyada `composer install --no-dev` ilə dev paketləri yoxdur.
