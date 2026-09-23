<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Support\QuestionTypes;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\GroupSeeder;
use Database\Seeders\SubjectGroupScoreSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `DemoContentSeeder` + `php artisan demo:clear`.
 *
 * Yoxlanılanlar: əhatə (hər düyündə imtahan və hər filtr ölçüsündə variant), idempotentlik
 * (iki dəfə işləyəndə data ikiləşmir) və təmizləmənin real dataya toxunmaması.
 */
class DemoContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // İctimai disk `Tests\TestCase`-də onsuz da saxtalaşdırılır

        // Rollar bazanın özü ilə birlikdə `Tests\TestCase`-də yüklənir
        $this->seed([
            SubjectSeeder::class,
            GroupSeeder::class,
            SubjectGroupScoreSeeder::class,
            CategorySeeder::class,
        ]);

        // Demo imtahanları və əl ilə verilən giriş hüququ yaradan admini göstərir
        User::factory()->create()->assignRole('admin');
    }

    public function test_seeder_fills_every_catalog_node_and_filter(): void
    {
        $this->seed(DemoContentSeeder::class);

        // Hər düyündə dörd növün hamısı və hər iki qiymət variantı olmalıdır
        foreach (\Database\Seeders\Demo\DemoCatalog::nodes() as $node) {
            $category = Category::where('path', $node['path'])->first();
            $this->assertNotNull($category, "Kateqoriya yoxdur: {$node['path']}");

            $sector = ($node['az'] ?? true) ? 'az' : 'ru';
            // Düyünün ÖZ imtahanları; kataloq səhifəsi bunlara alt düyünlərinkini də əlavə edir
            $exams = Exam::where('category_id', $category->id)->visible($sector)->get();

            /*
                Adi düyün dörd növün hamısını alır. Sinif etiketi nümunəsi olan düyün isə
                (`practice_grades`) yalnız məşq testi qurur — hər sinif üçün ayrıca.
            */
            $kinds = $node['kinds'] ?? Exam::KINDS;
            $expected = count($kinds) - (isset($node['practice_grades']) ? 1 : 0)
                + count($node['practice_grades'] ?? []);

            $this->assertCount($expected, $exams, "{$expected} imtahan gözlənilirdi: {$node['path']}");
            $this->assertEqualsCanonicalizing($kinds, $exams->pluck('kind')->unique()->all());
            $this->assertTrue($exams->contains('is_free', true), "Pulsuz imtahan yoxdur: {$node['path']}");
            $this->assertTrue($exams->contains('is_free', false), "Pullu imtahan yoxdur: {$node['path']}");

            if (in_array(Exam::KIND_TOPIC_TRIAL, $kinds, true)) {
                $this->assertTrue($exams->where('kind', Exam::KIND_TOPIC_TRIAL)->whereNotNull('quarter')->isNotEmpty());
            }

            foreach ($exams as $exam) {
                // Şagird tərəfdə "demo" sözü görünmür: başlıq və izah təmizdir
                $this->assertStringNotContainsStringIgnoringCase('demo', $exam->title);
                $this->assertNull($exam->description);
                // Slug-dakı `demo-` qalır: mövcud ünvanlar qırılmasın
                $this->assertStringStartsWith('demo-', $exam->slug);
                $this->assertTrue($exam->is_demo);
                $count = $exam->questions()->count();
                $this->assertGreaterThanOrEqual(8, $count, "Az sual: {$exam->slug}");
                $this->assertLessThanOrEqual(12, $count, "Çox sual: {$exam->slug}");
                $this->assertSame($count, (int) $exam->sections()->sum('question_count'));
            }
        }
    }

    /**
     * Sinif etiketi yalnız kateqoriya adı sinfi göstərməyəndə işlədilir.
     *
     * Buraxılış, abituriyent, magistratura, dövlət qulluğu, MİQ və sürücülük imtahanlarında
     * etiket kateqoriyanın adını təkrarlayardı — orada olmamalıdır. Nümunə üçün yalnız
     * neytral adlı düyünün məşq testləri etiketlənir ki, kataloqun "Sinif" filtri boş qalmasın.
     */
    public function test_grade_tags_are_only_used_where_the_category_does_not_state_a_grade(): void
    {
        $this->seed(DemoContentSeeder::class);

        $tagged = Exam::demo()->whereHas('tags', fn ($query) => $query->where('kind', 'grade'))->with('category')->get();

        $this->assertNotEmpty($tagged, 'Sinif etiketli demo imtahan yoxdur: filtr boş qalır.');

        foreach ($tagged as $exam) {
            $this->assertFalse(
                $exam->category->mentionsGrade(),
                "Kateqoriya onsuz da sinif bildirir: {$exam->category->path}",
            );
            $this->assertSame(Exam::KIND_PRACTICE, $exam->kind, "Etiket məşq testində olmalıdır: {$exam->slug}");
        }

        // Ən azı iki nümunə və həm ibtidai, həm orta sinif səviyyəsi
        $grades = $tagged->flatMap(fn (Exam $exam) => $exam->tags->pluck('order'))->unique();
        $this->assertGreaterThanOrEqual(2, $tagged->count());
        $this->assertTrue($grades->contains(fn (int $order) => $order <= 4), 'İbtidai sinif nümunəsi yoxdur.');
        $this->assertTrue($grades->contains(fn (int $order) => $order >= 5 && $order <= 8), 'Orta sinif nümunəsi yoxdur.');

        // Buraxılış və abituriyent imtahanlarında sinif etiketi olmamalıdır
        foreach (['mekteb/9-cu-sinif-buraxilis', 'mekteb/11-ci-sinif-buraxilis', 'abituriyent/2-ci-qrup', 'miq', 'suruculuk-imtahani/biletler'] as $path) {
            $exams = Exam::demo()->whereHas('category', fn ($query) => $query->where('path', $path))->get();

            $this->assertNotEmpty($exams, "Demo imtahan yoxdur: {$path}");

            foreach ($exams as $exam) {
                $this->assertSame([], $exam->tags()->pluck('tags.id')->all(), "Sinif etiketi qalıb: {$exam->slug}");
            }
        }
    }

    /**
     * DİM-in bütün açıq tapşırıq alt növləri demo datada olmalıdır: əks halda interfeys
     * (checkbox, sıralama, uyğunluq seçimi) heç vaxt sınaqdan keçmir.
     */
    public function test_every_dim_subtype_is_present_in_the_demo_bank(): void
    {
        $this->seed(DemoContentSeeder::class);

        foreach (Question::CODED_SUBTYPES as $subtype) {
            $this->assertTrue(
                Question::demo()->where('subtype', $subtype)->exists(),
                "Kodlaşdırılan alt növ yoxdur: {$subtype}",
            );
        }

        foreach ([Question::WRITTEN_FREE, Question::WRITTEN_SITUATION, Question::WRITTEN_TEXT, Question::WRITTEN_SOURCE] as $subtype) {
            $this->assertTrue(
                Question::demo()->where('subtype', $subtype)->exists(),
                "Yazılı alt növ yoxdur: {$subtype}",
            );
        }

        // Uyğunluq cütləri sıralı saxlanılır və düzgün cavab onlardan hesablanır
        $matching = Question::demo()->where('subtype', Question::CODED_MATCHING)->firstOrFail();
        $this->assertGreaterThanOrEqual(2, count($matching->pairs));
        $this->assertSame('1-1,2-2,3-3', \App\Support\CodedAnswer::correct($matching));

        // Bir mətnə bir neçə sual bağlanır (mətn və mənbə əsaslı tapşırıqlar)
        $passage = \App\Models\Passage::demo()
            ->withCount('questions')
            ->orderByDesc('questions_count')
            ->firstOrFail();

        $this->assertGreaterThanOrEqual(2, $passage->questions_count);
    }

    public function test_russian_sector_and_group_rules(): void
    {
        $this->seed(DemoContentSeeder::class);

        // Rus sektoru açıq kateqoriyalarda ru imtahanı və rusca sual olmalıdır
        $ruExams = Exam::demo()->where('sector', 'ru')->get();
        $this->assertGreaterThanOrEqual(2, $ruExams->count());

        $ruExam = $ruExams->first();
        $this->assertSame('ru', $ruExam->questions()->first()->language);

        // Abituriyent qrupunda bal qrupu var, sürücülük/MİQ imtahanında olmamalıdır
        $abiturient = Exam::demo()->whereHas('category', fn ($query) => $query->where('path', 'abituriyent/2-ci-qrup'))->first();
        $this->assertNotNull($abiturient->group_id);

        $driving = Exam::demo()->whereHas('category', fn ($query) => $query->where('path', 'suruculuk-imtahani/biletler'))->first();
        $this->assertNull($driving->group_id);
    }

    public function test_question_bank_mixes_types_formulas_and_topics(): void
    {
        $this->seed(DemoContentSeeder::class);

        foreach (Question::TYPES as $type) {
            $this->assertTrue(Question::demo()->where('type', $type)->exists(), "Sual növü yoxdur: {$type}");
        }

        // Variant sayları qarışıq: 4 və 5 variantlı qapalı suallar
        $optionCounts = Question::demo()->multipleChoice()->withCount('options')->pluck('options_count')->unique();
        $this->assertContains(4, $optionCounts->all());
        $this->assertContains(5, $optionCounts->all());

        // KaTeX düsturu və uzun mətn
        $this->assertTrue(Question::demo()->where('question_text', 'like', '%\\frac%')->exists());
        $this->assertTrue(Question::demo()->whereRaw('LENGTH(question_text) > 400')->exists());

        // Sual mətnlərində "demo" sözü olmur
        $this->assertSame(0, Question::demo()->where('question_text', 'like', '%demo%')->count());

        // Hər sual mövzuya bağlıdır və mövzular rüblərə bölünüb
        $this->assertSame(0, Question::demo()->whereNull('topic_id')->count());
        $this->assertEqualsCanonicalizing([1, 2, 3, 4], Topic::where('is_demo', true)->pluck('quarter')->unique()->sort()->values()->all());
    }

    /** Şəkilli suallar: sürücülük imtahanlarında yol nişanı SVG-ləri. */
    public function test_driving_questions_carry_generated_road_sign_images(): void
    {
        $this->seed(DemoContentSeeder::class);

        $driving = Subject::where('slug', 'yol-hereketi-qaydalari')->firstOrFail();
        $questions = Question::demo()->where('subject_id', $driving->id)->get();

        $withImage = $questions->whereNotNull('question_image');

        // Sürücülük suallarının çoxu şəkillidir
        $this->assertGreaterThan($questions->count() / 2, $withImage->count());

        foreach ($withImage as $question) {
            // Şəkil məzmunun özüdür: alt mətni mütləqdir və cavabı verməməlidir
            $this->assertNotEmpty($question->question_image_alt);
            $this->assertTrue(Storage::disk('public')->exists($question->question_image));
            $this->assertStringEndsWith('.svg', $question->question_image);
        }
    }

    /**
     * Alt mətni CAVABI AÇMAMALIDIR.
     *
     * Şəkil açılmayanda (və ya ekran oxuyucusunda) şagird yalnız `alt` mətnini görür.
     * "Dairəvi nişan: qırmızı fon, ağ üfüqi zolaq" birbaşa "giriş qadağandır" deməkdir —
     * belə təsvir sualı mənasız edir.
     */
    public function test_demo_image_alt_text_does_not_leak_the_answer(): void
    {
        $this->seed(DemoContentSeeder::class);

        $questions = Question::demo()->with('options')->whereNotNull('question_image')->get();

        $this->assertGreaterThan(0, $questions->count());

        foreach ($questions as $question) {
            $alt = mb_strtolower(trim((string) $question->question_image_alt));

            $this->assertNotEmpty($alt, "Alt mətni boşdur: {$question->id}");

            // Alt mətni yalnız şəklin NÖVÜNÜ bildirir
            $this->assertContains($alt, ['yol nişanı', 'yolayrıcı sxemi'], "Alt mətni çox danışır: «{$alt}»");

            foreach ($question->options as $option) {
                $optionText = mb_strtolower(trim((string) $option->option_text));

                // Nə variantın mətni alt-da, nə alt variantın içində olmalıdır
                $this->assertStringNotContainsString($optionText, $alt);
                $this->assertStringNotContainsString($alt, $optionText);
            }
        }
    }

    /** Şəkil URL-i `Storage::disk('public')->url()` ilə qurulur, əl ilə birləşdirilmir. */
    public function test_demo_image_urls_are_built_from_the_storage_disk(): void
    {
        $this->seed(DemoContentSeeder::class);

        $question = Question::demo()->whereNotNull('question_image')->firstOrFail();

        $this->assertSame(
            Storage::disk('public')->url($question->question_image),
            $question->imageUrl(),
        );

        // Model şablona bütöv göndəriləndə də URL gedir
        $this->assertSame($question->imageUrl(), $question->toArray()['question_image_url']);

        // Şəkli olmayan sualda URL null-dur (şablon boş <img> render etməsin)
        $this->assertNull(Question::demo()->whereNull('question_image')->firstOrFail()->imageUrl());
    }

    /** İmtahan növünə görə icazəli tiplər nümunə məzmunda da tətbiq olunur. */
    public function test_demo_exams_respect_the_allowed_question_types(): void
    {
        $this->seed(DemoContentSeeder::class);

        foreach (Exam::demo()->with('category')->get() as $exam) {
            $allowed = QuestionTypes::forExam($exam);
            $types = $exam->questions()->pluck('questions.type')->unique();

            foreach ($types as $type) {
                $this->assertContains(
                    $type,
                    $allowed,
                    "İcazəsiz sual tipi: {$exam->slug} ({$exam->category?->path}) → {$type}",
                );
            }
        }

        // Sürücülük və MİQ imtahanlarında açıq sual qalmamalıdır
        foreach (['suruculuk-imtahani', 'miq', 'muellimler'] as $path) {
            $open = Exam::demo()
                ->whereHas('category', fn ($query) => $query->where('path', 'like', $path.'%'))
                ->whereHas('questions', fn ($query) => $query->whereIn('questions.type', [
                    Question::TYPE_OPEN_CODED,
                    Question::TYPE_OPEN_WRITTEN,
                ]))
                ->count();

            $this->assertSame(0, $open, "«{$path}» ağacında açıq sual qaldı.");
        }
    }

    public function test_demo_students_have_scored_attempts(): void
    {
        $this->seed(DemoContentSeeder::class);

        $students = User::where('is_demo', true)->get();
        $this->assertCount(2, $students);

        foreach ($students as $student) {
            $attempts = ExamAttempt::where('user_id', $student->id)->get();
            $this->assertGreaterThan(0, $attempts->count());
            $this->assertTrue($attempts->contains(fn (ExamAttempt $attempt) => $attempt->status === ExamAttempt::STATUS_COMPLETED));
            $this->assertTrue($attempts->every(fn (ExamAttempt $attempt) => $attempt->sectionResults()->exists()));
        }

        // Cəhdlər həm pulsuz, həm pullu imtahanları əhatə edir — pullu imtahan üçün
        // giriş hüququ da açılır, yəni alış axını da real data ilə yoxlana bilir
        $paidAttempts = ExamAttempt::whereHas('exam', fn ($query) => $query->where('is_free', false))->count();
        $this->assertGreaterThan(0, $paidAttempts, 'Pullu imtahana demo cəhd yoxdur.');
        $this->assertGreaterThan(0, DB::table('exam_accesses')->count());

        // Bir neçə cəhd yoxlanmamış yazılı cavabla qalır: admin qiymətləndirmə ekranı boş olmasın
        $this->assertTrue(ExamAttempt::where('status', ExamAttempt::STATUS_PENDING_REVIEW)->exists());

        // Statistikanın "zəif mövzular" bölməsi mövzuya bağlı cavablardan qurulur
        $withTopic = DB::table('attempt_answers')
            ->join('questions', 'questions.id', '=', 'attempt_answers.question_id')
            ->whereNotNull('questions.topic_id')
            ->count();

        $this->assertGreaterThan(0, $withTopic);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DemoContentSeeder::class);

        $before = $this->counts();

        $this->seed(DemoContentSeeder::class);

        $this->assertSame($before, $this->counts());
    }

    public function test_clear_removes_demo_data_but_keeps_real_data(): void
    {
        $this->seed(DemoContentSeeder::class);

        $realQuestion = Question::create([
            'subject_id' => Subject::first()->id,
            'question_text' => 'Real sual',
            'type' => Question::TYPE_OPEN_CODED,
            'accepted_answers' => ['1'],
            'language' => 'az',
        ]);

        $realTopic = Topic::create([
            'subject_id' => Subject::first()->id,
            'name' => 'Real mövzu',
            'slug' => 'real-movzu',
        ]);

        $this->artisan('demo:clear', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Exam::withTrashed()->where('is_demo', true)->count());
        $this->assertSame(0, Question::where('is_demo', true)->count());
        $this->assertSame(0, Topic::where('is_demo', true)->count());
        $this->assertSame(0, User::where('is_demo', true)->count());
        $this->assertSame(0, DB::table('exam_attempts')->count());
        $this->assertSame(0, DB::table('attempt_answers')->count());
        $this->assertSame(0, DB::table('exam_sections')->count());

        $this->assertModelExists($realQuestion);
        $this->assertModelExists($realTopic);
        // Real fənlər və kateqoriyalar demo deyil, qalmalıdır
        $this->assertTrue(Subject::where('slug', 'yol-hereketi-qaydalari')->exists());
        $this->assertSame(54, Category::count());
    }

    public function test_clear_keeps_demo_questions_used_by_real_exams(): void
    {
        $this->seed(DemoContentSeeder::class);

        $realExam = Exam::create([
            'subject_id' => Subject::first()->id,
            'title' => 'Real imtahan',
            'duration_minutes' => 60,
            'sector' => 'az',
        ]);

        $section = $realExam->sections()->create(['subject_id' => $realExam->subject_id, 'order' => 1]);
        $borrowed = Question::demo()->where('subject_id', $realExam->subject_id)->firstOrFail();
        $realExam->questions()->attach($borrowed->id, ['section_id' => $section->id, 'order' => 1]);

        $this->artisan('demo:clear', ['--force' => true])->assertSuccessful();

        $this->assertModelExists($borrowed);
        $this->assertModelExists($realExam);
        $this->assertSame(1, $realExam->questions()->count());
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'exams' => Exam::withTrashed()->count(),
            'sections' => DB::table('exam_sections')->count(),
            'exam_question' => DB::table('exam_question')->count(),
            'questions' => Question::count(),
            'options' => DB::table('question_options')->count(),
            'topics' => Topic::count(),
            'users' => User::count(),
            'attempts' => DB::table('exam_attempts')->count(),
            'answers' => DB::table('attempt_answers')->count(),
            'accesses' => DB::table('exam_accesses')->count(),
        ];
    }
}
