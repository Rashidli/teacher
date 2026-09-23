# Deploy qaydası

Bu sənəd produksiyaya çıxarma addımlarını təsbit edir. **Hazırda kod birbaşa produksiya
qovluğunda yazılır** — staging mühiti qurulandan sonra (P2.5) qayda dəyişəcək:
dəyişiklik staging-də edilir, produksiyaya yalnız `git pull` ilə çıxarılır.

## Qovluqlar

| Mühit | Qovluq | Baza | URL |
|---|---|---|---|
| Produksiya (müvəqqəti domen) | `/home/websites/web/teacher.cvhazirla.az/public_html` | `websites_teacher_exam_db` | https://teacher.cvhazirla.az |
| Produksiya (əsl domen) | *(domen alınanda)* | *(yeni baza)* | *(yeni domen)* |

Əsl domen alınanda **ayrıca staging subdomeni qurulmur**: yeni domen produksiya olur,
`teacher.cvhazirla.az` isə staging-ə çevrilir (aşağıda "Domen keçidi").

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
php artisan queue:restart             # 7. Növbə işçisi YENİ kodu götürsün
```

**7-ci addım vacibdir:** `queue:work` prosesi kodu bir dəfə yükləyir və yaddaşda saxlayır.
`queue:restart` olmadan işçi köhnə kodla işləməyə davam edir — deploy-dan sonrakı işlər
köhnə məntiqlə icra olunar. Əmr işçiyə siqnal göndərir, o cari işi bitirib dayanır,
systemd isə onu dərhal geri qaldırır (`Restart=always`).

Kateqoriya ağacı və ya bal matrisi dəyişibsə, əlavə olaraq:

```bash
php artisan db:seed --class=SubjectGroupScoreSeeder --force
php artisan db:seed --class=CategorySeeder --force
```

Seeder-lər idempotentdir (təkrar işlədilə bilər). `migrate:fresh` **işlədilmir**.

## Növbə işçisi (systemd)

Açıq yazılı cavabların avtomatik qiymətləndirilməsi növbə ilə işləyir. **Serverdə supervisor
quraşdırılmayıb** (34 sayt, PID 1 systemd-dir), ona görə işçi systemd xidmətidir — yeni paket
lazım deyil və xidmət yalnız bu sayta aiddir.

Konfiqurasiya repodadır: **`deploy/teacher-queue.service`**. İlk dəfə quraşdırma (root):

```bash
cp deploy/teacher-queue.service /etc/systemd/system/teacher-queue.service
systemctl daemon-reload
systemctl enable --now teacher-queue
systemctl status teacher-queue --no-pager
```

Yoxlama və loglar:

```bash
systemctl status teacher-queue --no-pager
tail -f storage/logs/queue.log
php artisan queue:failed          # uğursuz işlər
```

`.env`-də **`ANTHROPIC_API_KEY`** boşdursa avtomatik qiymətləndirmə söndürülür: cavablar
`pending_review` qalır və admin əl ilə qiymətləndirir. Sistem sınmır.

Laravel planlayıcısı üçün cron sətri: **`deploy/crontab.txt`** (bu saytda yox idi).

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

## Axtarış sistemləri: `SEO_INDEXING`

`.env`-dəki `SEO_INDEXING` bayrağı bütün SEO davranışını bir yerdən idarə edir:

| Dəyər | Nəticə |
|---|---|
| `false` (indiki hal) | Hər səhifədə `<meta name="robots" content="noindex, nofollow">` və `X-Robots-Tag` başlığı; `robots.txt` → `Disallow: /`, sitemap göstərilmir |
| `true` | Normal indeksləşmə; `robots.txt` panel ünvanlarını bağlayır və sitemap-ı göstərir |

İndeksləşmə yalnız `SEO_INDEXING=true` **və** `APP_ENV=production` olanda açılır — staging və
lokal nüsxə bayraqdan asılı olmayaraq bağlıdır (`App\Support\Seo::indexable()`).
Dəyişikliyi tətbiq etmək üçün: `php artisan config:clear`.

`robots.txt` statik fayl deyil, route-dur (`RobotsController`) — `public/robots.txt` yaratmaq
olmaz, əks halda veb server statik faylı verər və bayraq işləməz.

## Domen keçidi (əsl domen alınanda)

1. Yeni domen üçün qovluq, baza və `.env` hazırlanır:
   `APP_URL=<yeni domen>`, `APP_ENV=production`, **`SEO_INDEXING=true`**, real `PAYMENT_DRIVER`,
   ayrıca `APP_KEY`.
2. Kod `git pull` ilə çıxarılır, yuxarıdakı deploy addımları icra olunur.
3. `teacher.cvhazirla.az` staging-ə çevrilir:
   - `.env`: `APP_ENV=staging`, `APP_DEBUG=false`, **`SEO_INDEXING=false`**,
     `PAYMENT_DRIVER=fake`, `MAIL_MAILER=log`;
   - `php artisan staging:anonymize --password=<parol>` (admin hesabları toxunulmur,
     əmr produksiyada işləmir);
   - mümkünsə HTTP basic auth.
4. Yeni domendə Search Console-a sitemap göndərilir; istənilsə köhnə domendən 301 qoyulur.
5. İş qaydası: dəyişiklik staging-də yoxlanılır, produksiyaya yalnız `git pull` ilə çıxarılır.
   Produksiyada `composer install --no-dev` olduğuna görə testlər staging-də işləyir.
