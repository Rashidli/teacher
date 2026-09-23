<?php

namespace Database\Seeders;

use App\Http\Controllers\SitemapController;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use App\Models\Question;
use App\Services\Scoring\AttemptScorer;
use App\Support\QuestionTypes;
use App\Support\Sector;
use Database\Seeders\Demo\DemoBankBuilder;
use Database\Seeders\Demo\DemoCatalog;
use Database\Seeders\Demo\DemoExamBuilder;
use Database\Seeders\Demo\DemoQuestionFactory;
use Database\Seeders\Demo\DemoRoadSigns;
use Database\Seeders\Demo\DemoStudentBuilder;
use Database\Seeders\Demo\DemoTaxonomy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Nümunə (demo) məzmun: kataloqun heç bir düyünü və heç bir filtri boş qalmasın.
 *
 * `php artisan db:seed --class=DemoContentSeeder --force`
 *
 * Yaradılanlar — hamısı `is_demo = 1`:
 *   - hər fənn üçün rüblərə bölünmüş 4 mövzu (mövzu sınağı generasiyası və statistikadakı
 *     "zəif mövzular" bölməsi bunlarsız işləmir);
 *   - hər fənn/dil üçün sual bankı (qapalı 4 və 5 variantlı, açıq kodlaşdırılan, açıq yazılı;
 *     bir hissəsində KaTeX düsturu, bir hissəsində uzun mətn);
 *   - `DemoCatalog`-dakı hər düyün üçün 4 imtahan (ümumi / mövzu / fənn / məşq), qiymətlər
 *     qarışıq, hamısı dərc olunmuş;
 *   - rus sektoru açıq olan düyünlərdə eyni dəst ru sektorunda, adları və sualları rusca;
 *   - hər sektor üçün bir demo şagird və onun tamamlanmış cəhdləri.
 *
 * TƏHLÜKƏSİZLİK: produksiyada təsdiqsiz işləmir. `db:seed` özü `--force` tələb edir, əlavə
 * olaraq seeder də mühiti yoxlayır — `DatabaseSeeder`-ə QOŞULMAYIB ki, adi `db:seed` onu
 * təsadüfən çağırmasın.
 *
 * ŞAGİRD TƏRƏFDƏ "DEMO" SÖZÜ GÖRÜNMÜR: imtahan adları və sual mətnləri təmizdir, nümunə
 * məzmun real məzmundan seçilmir. Ayırd etmə `is_demo` bayrağı ilədir — admin panel onu
 * nişanla göstərir, `php artisan demo:clear` ona görə silir. Slug-lardakı `demo-` hissəsi
 * isə qalır: mövcud ünvanlar qırılmasın.
 *
 * Bütün yazılar idempotentdir (mövzu: slug, sual: `source`, imtahan: `slug`), ona görə
 * seeder istənilən qədər təkrar işlədilə bilər. Təmizləmə: `php artisan demo:clear`.
 */
class DemoContentSeeder extends Seeder
{
    /** Rus sektoru imtahanlarında işlənən fənlər — yalnız onlara ru sualı qurulur */
    private const RU_SUBJECTS = [
        'azerbaycan-dili', 'rus-dili', 'edebiyyat', 'tarix', 'cografiya',
        'riyaziyyat', 'fizika', 'kimya', 'biologiya', 'informatika', 'ingilis-dili',
    ];

    public function run(): void
    {
        if (! $this->confirmEnvironment()) {
            return;
        }

        $subjects = Subject::whereIn('slug', array_keys(DemoTaxonomy::all()))->get()->keyBy('slug')->all();
        $missing = array_diff(array_keys(DemoTaxonomy::all()), array_keys($subjects));

        if ($missing !== []) {
            $this->command?->warn(
                'Bu fənlər bazada yoxdur, atlanır: '.implode(', ', $missing)
                .' — əvvəlcə `php artisan db:seed --class=SubjectSeeder --force` işlədin.'
            );
        }

        $adminId = User::whereHas('roles', fn ($query) => $query->where('name', 'admin'))->value('id');

        $categories = Category::whereIn('path', array_column(DemoCatalog::nodes(), 'path'))
            ->get()
            ->keyBy('path')
            ->all();

        // Sürücülük suallarının şəkilləri: SVG-lər burada yazılır (xarici fayl yüklənmir)
        $this->writeRoadSigns();

        $bank = new DemoBankBuilder(new DemoQuestionFactory);
        $bank->build($subjects, self::RU_SUBJECTS, $this->allowedTypesBySubject($categories));

        $exams = new DemoExamBuilder($bank, $subjects, $adminId);
        $exams->build($categories);

        $students = new DemoStudentBuilder(app(AttemptScorer::class));
        $students->build($exams->exams, $adminId);

        SitemapController::forget();

        $this->summary($bank->stats + $exams->stats + $students->stats);
        $this->credentials($students->credentials);
        $this->coverage();
    }

    /**
     * Fənn üzrə icazəli sual tipləri.
     *
     * Sual bankı fənnə aiddir, qayda isə imtahanın KATEQORİYASINA. Bir fənn bir neçə
     * kateqoriyada işlənə bilər (məs. Riyaziyyat həm abituriyentdə, həm MİQ-də), ona görə
     * fənnin bankı onu işlədən kateqoriyaların BİRLƏŞMƏSİ qədər geniş olur; konkret imtahan
     * isə öz kateqoriyasının qaydasına görə süzülür (`DemoExamBuilder::pickQuestions()`).
     *
     * Yalnız qapalı sual qəbul edən kateqoriyalarda işlənən fənn (Yol hərəkəti qaydaları,
     * Kurikulum və metodika) bankda da yalnız qapalı sual alır — əks halda rüb sınağı üçün
     * icazəli sual çatmazdı.
     *
     * @param  array<string, Category>  $categories
     * @return array<string, array<int, string>>
     */
    private function allowedTypesBySubject(array $categories): array
    {
        $allowed = [];

        foreach (DemoCatalog::nodes() as $node) {
            $types = QuestionTypes::forCategory($categories[$node['path']] ?? null);
            $sets = array_merge($node['sets'] ?? [], $node['ru_sets'] ?? []);

            foreach ($sets as $set) {
                foreach ($set as $slug) {
                    $allowed[$slug] = array_values(array_unique(array_merge($allowed[$slug] ?? [], $types)));
                }
            }
        }

        // Kataloqda işlənməyən fənlər (bank üçün) hər üç tipi alır
        foreach (array_keys(DemoTaxonomy::all()) as $slug) {
            $allowed[$slug] ??= Question::TYPES;
        }

        return $allowed;
    }

    /**
     * Yol nişanı SVG-lərini `storage/app/public` altına yazır.
     *
     * Məzmun deterministikdir, ona görə təkrar işə salmada eyni fayl yazılır. `demo:clear`
     * qovluğu bütöv silir.
     */
    private function writeRoadSigns(): void
    {
        $disk = Storage::disk('public');

        foreach (array_merge(DemoRoadSigns::all(), DemoRoadSigns::junctions()) as $sign) {
            $disk->put(DemoRoadSigns::DIRECTORY.'/'.$sign['key'].'.svg', $sign['svg']);
        }
    }

    /**
     * Produksiyada seeder yalnız açıq təsdiqlə işləyir.
     *
     * `db:seed --force` interaktiv təsdiqi keçir (Laravel-in öz qoruyucusu); əmr interaktiv
     * işləyirsə burada ayrıca sual verilir. Hər ikisi olmadan seeder heç nə yazmır.
     */
    private function confirmEnvironment(): bool
    {
        if (! app()->isProduction()) {
            return true;
        }

        $this->command?->warn('DİQQƏT: mühit PRODUKSİYADIR (APP_ENV=production).');
        $this->command?->warn(
            'Bazaya nümunə imtahanlar, suallar və demo şagird hesabları yazılacaq. '
            .'Hamısı `is_demo` ilə işarələnir və `php artisan demo:clear` ilə silinir.'
        );

        if ($this->command === null) {
            return false;
        }

        // --force verilibsə `db:seed` təsdiqi artıq keçib; interaktiv rejimdə yenidən soruşulur
        if ($this->command->option('force')) {
            return true;
        }

        if (! $this->command->input->isInteractive()) {
            $this->command->error('Təsdiq yoxdur. `--force` ilə işlədin.');

            return false;
        }

        return $this->command->confirm('Davam edilsin?', false);
    }

    /** @param  array<string, int>  $stats */
    private function summary(array $stats): void
    {
        $this->command?->newLine();
        $this->command?->info('Demo məzmun hazırdır.');
        $this->command?->table(
            ['Göstərici', 'Say'],
            [
                ['Mövzu (yeni)', $stats['topics_created']],
                ['Sual (yeni / yenilənmiş)', $stats['questions_created'].' / '.$stats['questions_updated']],
                ['İmtahan (yeni / yenilənmiş)', $stats['exams_created'].' / '.$stats['exams_updated']],
                ['Demo şagird (yeni)', $stats['students']],
                ['Cəhd (yeni)', $stats['attempts_created']],
                ['Giriş hüququ (yeni)', $stats['accesses']],
            ],
        );
    }

    /** @param  array<int, array{email: string, password: string, sector: string}>  $credentials */
    private function credentials(array $credentials): void
    {
        if ($credentials === []) {
            return;
        }

        $this->command?->newLine();
        $this->command?->warn('Demo şagird hesabları — parol yalnız indi göstərilir, saxlanılmır:');
        $this->command?->table(
            ['Sektor', 'E-poçt', 'Parol'],
            array_map(
                fn (array $row) => [$row['sector'], $row['email'], $row['password']],
                $credentials,
            ),
        );
    }

    /**
     * Əhatə hesabatı: hansı kateqoriyada neçə imtahan var və hansı filtr variantı boşdur.
     *
     * Sayğaclar kataloq səhifəsi ilə eyni məntiqlə hesablanır: düyünün BÜTÜN alt ağacı,
     * yalnız dərc olunmuş və aktiv imtahanlar, sektora görə ayrıca.
     */
    private function coverage(): void
    {
        $rows = [];
        $gaps = [];

        $categories = Category::active()->where('has_exams', true)->orderBy('path')->get();

        foreach ($categories as $category) {
            $ids = $category->subtreeIds();
            $counts = [];

            foreach (Sector::ALL as $sector) {
                $exams = Exam::with('sections')
                    ->whereIn('category_id', $ids)
                    ->visible($sector)
                    ->get();

                $counts[$sector] = $exams;

                if ($sector === Sector::RU && ! $category->ru_enabled) {
                    continue;
                }

                foreach ($this->emptyFilters($exams) as $gap) {
                    $gaps[] = [$category->path, $sector, $gap];
                }
            }

            $rows[] = [
                $category->path,
                $counts[Sector::AZ]->count(),
                $category->ru_enabled ? $counts[Sector::RU]->count() : '—',
            ];
        }

        $this->command?->newLine();
        $this->command?->info('Kateqoriya üzrə imtahan sayı (alt ağac daxil, dərc olunmuş):');
        $this->command?->table(['Kateqoriya', 'az', 'ru'], $rows);

        $this->command?->newLine();

        if ($gaps === []) {
            $this->command?->info('Boş filtr variantı yoxdur: hər düyündə növ, rüb, fənn və qiymət seçimləri doludur.');

            return;
        }

        $this->command?->warn('Boş qalan filtr variantları:');
        $this->command?->table(['Kateqoriya', 'Sektor', 'Boşluq'], $gaps);
    }

    /**
     * Kataloq filtr panelinin boş qalan ölçüləri.
     *
     * `CategoryController::filterOptions()` yalnız MÖVCUD variantları göstərir, ona görə
     * "boşluq" = həmin ölçüdə heç bir variantın olmaması.
     *
     * @param  Collection<int, Exam>  $exams
     * @return array<int, string>
     */
    private function emptyFilters(Collection $exams): array
    {
        if ($exams->isEmpty()) {
            return ['imtahan yoxdur'];
        }

        $gaps = [];

        $kinds = $exams->pluck('kind')->unique();

        foreach (Exam::KINDS as $kind) {
            if (! $kinds->contains($kind)) {
                $gaps[] = 'növ: '.$kind;
            }
        }

        if ($exams->where('kind', Exam::KIND_TOPIC_TRIAL)->pluck('quarter')->filter()->isEmpty()) {
            $gaps[] = 'rüb: mövzu sınağı yoxdur';
        }

        if ($exams->flatMap(fn (Exam $exam) => $exam->sections->pluck('subject_id'))->filter()->isEmpty()) {
            $gaps[] = 'fənn: bölmə yoxdur';
        }

        if ($exams->where('is_free', true)->isEmpty()) {
            $gaps[] = 'qiymət: pulsuz yoxdur';
        }

        if ($exams->where('is_free', false)->isEmpty()) {
            $gaps[] = 'qiymət: pullu yoxdur';
        }

        return $gaps;
    }
}
