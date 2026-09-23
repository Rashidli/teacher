<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Support\QuestionTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * İmtahan növünə görə icazəli sual tipləri (`config/questions.php`).
 *
 * Sürücülük və MİQ-də açıq sual yoxdur, dövlət qulluğunun BB/AC qrupunda da yalnız qapalı
 * test var. Qayda üç yerdə tətbiq olunur: admin sual forması, bankdan generasiya və
 * nümunə məzmun seeder-i.
 */
class QuestionTypeRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->subject = Subject::create([
            'name' => 'Yol hərəkəti qaydaları',
            'slug' => 'yol-hereketi-qaydalari',
            'category' => 'technical',
        ]);
    }

    private function examIn(string $path): Exam
    {
        $category = Category::create([
            'name' => $path, 'slug' => basename($path), 'path' => $path, 'has_exams' => true,
        ]);

        $exam = Exam::factory()->create([
            'category_id' => $category->id,
            'group_id' => Group::factory()->create()->id,
            'subject_id' => $this->subject->id,
            'options_per_question' => 4,
        ]);

        // Exam factory-si bölməni özü qura bilər
        ExamSection::firstOrCreate(
            ['exam_id' => $exam->id, 'subject_id' => $this->subject->id],
            ['order' => 1],
        );

        return $exam;
    }

    /** @return array<string, mixed> */
    private function payload(string $type): array
    {
        $base = [
            'question_text' => 'Şəkildəki yol nişanı nəyi bildirir?',
            'type' => $type,
            'difficulty' => 'medium',
        ];

        if ($type === Question::TYPE_MULTIPLE_CHOICE) {
            return $base + ['options' => collect(['A', 'B', 'C', 'D'])->map(fn ($letter, $i) => [
                'option_letter' => $letter,
                'option_text' => 'Variant '.$letter,
                'is_correct' => $i === 0,
            ])->all()];
        }

        if ($type === Question::TYPE_OPEN_CODED) {
            return $base + ['accepted_answers' => ['50']];
        }

        return $base;
    }

    /* ------------------------------------------------------------- qaydalar */

    public function test_the_rules_come_from_the_config(): void
    {
        $this->assertSame([Question::TYPE_MULTIPLE_CHOICE], QuestionTypes::forPath('suruculuk-imtahani/kateqoriyalar/b'));
        $this->assertSame([Question::TYPE_MULTIPLE_CHOICE], QuestionTypes::forPath('miq'));
        $this->assertSame([Question::TYPE_MULTIPLE_CHOICE], QuestionTypes::forPath('muellimler/sertifikasiya'));

        // Alt düyün valideyndən dəqiq qayda təyin edə bilər
        $this->assertSame([Question::TYPE_MULTIPLE_CHOICE], QuestionTypes::forPath('dovlet-qullugu/tam-sinaq/bb-ac'));
        $this->assertSame(
            [Question::TYPE_MULTIPLE_CHOICE, Question::TYPE_OPEN_WRITTEN],
            QuestionTypes::forPath('dovlet-qullugu/tam-sinaq/aa'),
        );

        // Buraxılış, abituriyent və magistratura hər üç tipi qəbul edir
        $this->assertCount(3, QuestionTypes::forPath('abituriyent/2-ci-qrup'));
        $this->assertCount(3, QuestionTypes::forPath('mekteb/9-cu-sinif-buraxilis'));
        $this->assertCount(3, QuestionTypes::forPath('magistratura/tam-sinaq'));

        // Kateqoriyası olmayan imtahan defolta düşür
        $this->assertCount(3, QuestionTypes::forPath(null));
    }

    /* ------------------------------------------------------ admin sual forması */

    public function test_a_driving_exam_accepts_only_closed_questions(): void
    {
        $exam = $this->examIn('suruculuk-imtahani');

        $this->actingAs($this->admin)
            ->post(route('admin.exams.questions.store', $exam), $this->payload(Question::TYPE_MULTIPLE_CHOICE))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.exams.questions.store', $exam), $this->payload(Question::TYPE_OPEN_CODED))
            ->assertSessionHasErrors('type');

        $this->actingAs($this->admin)
            ->post(route('admin.exams.questions.store', $exam), $this->payload(Question::TYPE_OPEN_WRITTEN))
            ->assertSessionHasErrors('type');

        $this->assertSame(1, Question::count());
    }

    public function test_an_abiturient_exam_accepts_all_three_types(): void
    {
        $exam = $this->examIn('abituriyent');

        foreach (Question::TYPES as $type) {
            $this->actingAs($this->admin)
                ->post(route('admin.exams.questions.store', $exam), $this->payload($type))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(3, Question::count());
    }

    /** Forma yalnız icazəli tipləri göstərsin deyə səhifəyə də ötürülür. */
    public function test_the_form_receives_the_allowed_types(): void
    {
        $driving = $this->examIn('miq');

        $this->actingAs($this->admin)
            ->get(route('admin.exams.questions.create', $driving))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('allowedTypes', [Question::TYPE_MULTIPLE_CHOICE]));
    }

    /* ------------------------------------------------------------ generasiya */

    /** Bankdan generasiya da qaydaya tabedir: açıq suallar hovuza düşmür. */
    public function test_generation_ignores_disallowed_types(): void
    {
        $exam = $this->examIn('suruculuk-imtahani/biletler');
        $category = $exam->category;

        $topic = Topic::create([
            'subject_id' => $this->subject->id,
            'name' => 'Yol nişanları',
            'slug' => 'yol-nisanlari',
            'quarter' => 1,
        ]);

        // Bankda 2 qapalı + 4 açıq sual var; generasiya 3 sual istəyir
        Question::factory()->count(2)->create([
            'subject_id' => $this->subject->id,
            'topic_id' => $topic->id,
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'language' => 'az',
        ]);
        Question::factory()->count(4)->create([
            'subject_id' => $this->subject->id,
            'topic_id' => $topic->id,
            'type' => Question::TYPE_OPEN_WRITTEN,
            'language' => 'az',
        ]);

        $result = app(\App\Services\ExamGeneration\ExamGenerator::class)->generate(
            category: $category,
            counts: [$this->subject->id => 3],
            quarter: 1,
            cumulative: false,
            variants: 1,
            attributes: [
                'title' => 'Sınaq',
                'duration_minutes' => 30,
                'options_per_question' => 4,
                'sector' => 'az',
            ],
        );

        // Açıq suallar sayılmır: yalnız 2 qapalı sual var, 3 istənilib
        $this->assertNotEmpty($result->shortfalls);
        $this->assertSame(2, $result->shortfalls[0]['available']);
    }
}
