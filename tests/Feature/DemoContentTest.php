<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\GroupSeeder;
use Database\Seeders\SubjectGroupScoreSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

            $this->assertCount(4, $exams, "4 imtahan gözlənilirdi: {$node['path']}");
            $this->assertEqualsCanonicalizing(Exam::KINDS, $exams->pluck('kind')->unique()->all());
            $this->assertTrue($exams->contains('is_free', true), "Pulsuz imtahan yoxdur: {$node['path']}");
            $this->assertTrue($exams->contains('is_free', false), "Pullu imtahan yoxdur: {$node['path']}");
            $this->assertTrue($exams->where('kind', Exam::KIND_TOPIC_TRIAL)->whereNotNull('quarter')->isNotEmpty());

            foreach ($exams as $exam) {
                $this->assertStringStartsWith('[DEMO]', $exam->title);
                $this->assertStringStartsWith('demo-', $exam->slug);
                $count = $exam->questions()->count();
                $this->assertGreaterThanOrEqual(8, $count, "Az sual: {$exam->slug}");
                $this->assertLessThanOrEqual(12, $count, "Çox sual: {$exam->slug}");
                $this->assertSame($count, (int) $exam->sections()->sum('question_count'));
            }
        }
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

        // Hər sual mövzuya bağlıdır və mövzular rüblərə bölünüb
        $this->assertSame(0, Question::demo()->whereNull('topic_id')->count());
        $this->assertEqualsCanonicalizing([1, 2, 3, 4], Topic::where('is_demo', true)->pluck('quarter')->unique()->sort()->values()->all());
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
