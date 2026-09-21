<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Bankdan imtahan yarat": kateqoriya + rüb + fənn üzrə sual sayı → təsadüfi seçim → qaralama.
 *
 * Ən vacib qaydalar: variantlar arasında suallar təkrarlanmır və bank çatmayanda HEÇ NƏ
 * yaradılmır (hansı fəndə neçə sual çatmadığı göstərilir).
 */
class ExamGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    private Subject $maths;

    private Subject $physics;

    private Topic $q1;

    private Topic $q2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $group = Group::factory()->create(['stage' => Group::STAGE_SECOND]);
        $this->maths = Subject::factory()->create(['name' => 'Riyaziyyat']);
        $this->physics = Subject::factory()->create(['name' => 'Fizika']);

        $this->category = Category::create([
            'name' => 'I qrup RK',
            'slug' => 'rk',
            'path' => 'abituriyent/1-ci-qrup/rk',
            'group_id' => $group->id,
        ]);
        $this->category->subjects()->attach([$this->maths->id, $this->physics->id]);

        $this->q1 = Topic::factory()->quarter(1)->create(['subject_id' => $this->maths->id]);
        $this->q2 = Topic::factory()->quarter(2)->create(['subject_id' => $this->maths->id]);
    }

    private function bank(Subject $subject, int $count, ?Topic $topic = null): void
    {
        Question::factory()->count($count)->withOptions(4)->create([
            'subject_id' => $subject->id,
            'topic_id' => $topic?->id,
        ]);
    }

    private function generate(array $overrides = [])
    {
        return $this->actingAs($this->admin, 'admin')->post(route('admin.exams.generate.store'), array_merge([
            'category_id' => $this->category->id,
            'variants' => 1,
            'title' => 'I qrup RK sınağı',
            'duration_minutes' => 180,
            'options_per_question' => 4,
            'counts' => [$this->maths->id => 3],
        ], $overrides));
    }

    public function test_it_creates_a_draft_exam_with_sections_and_questions(): void
    {
        $this->bank($this->maths, 5, $this->q2);
        $this->bank($this->physics, 4);

        $this->generate([
            'counts' => [$this->maths->id => 3, $this->physics->id => 2],
        ])->assertSessionHasNoErrors();

        $exam = Exam::firstOrFail();

        $this->assertFalse($exam->is_published, 'İmtahan qaralama olmalıdır');
        $this->assertFalse($exam->is_active);
        $this->assertSame($this->category->id, $exam->category_id);
        $this->assertSame(2, $exam->sections()->count());
        $this->assertSame(5, $exam->questions()->count());

        $mathsSection = $exam->sections()->where('subject_id', $this->maths->id)->firstOrFail();
        $this->assertSame(3, $mathsSection->questions()->count());
        $this->assertSame(3, $mathsSection->question_count);
    }

    /** Rüb seçiləndə yalnız həmin rübün mövzularından suallar düşür. */
    public function test_the_quarter_filters_the_pool(): void
    {
        $this->bank($this->maths, 3, $this->q1);
        $this->bank($this->maths, 3, $this->q2);

        $this->generate([
            'quarter' => 2,
            'counts' => [$this->maths->id => 3],
        ])->assertSessionHasNoErrors();

        $exam = Exam::firstOrFail();

        $this->assertSame(Exam::KIND_TOPIC_TRIAL, $exam->kind);
        $this->assertSame(2, $exam->quarter);

        foreach ($exam->questions as $question) {
            $this->assertSame($this->q2->id, $question->topic_id);
        }
    }

    /** Kumulyativ rübdə 1-ci və 2-ci rübün mövzuları birlikdə götürülür. */
    public function test_a_cumulative_quarter_includes_earlier_topics(): void
    {
        $this->bank($this->maths, 2, $this->q1);
        $this->bank($this->maths, 2, $this->q2);

        $this->generate([
            'quarter' => 2,
            'is_cumulative' => true,
            'counts' => [$this->maths->id => 4],
        ])->assertSessionHasNoErrors();

        $topics = Exam::firstOrFail()->questions->pluck('topic_id')->unique();

        $this->assertTrue($topics->contains($this->q1->id));
        $this->assertTrue($topics->contains($this->q2->id));
    }

    /** Variantlar arasında suallar təkrarlanmamalıdır. */
    public function test_variants_do_not_share_questions(): void
    {
        $this->bank($this->maths, 9, $this->q2);

        $this->generate([
            'variants' => 3,
            'quarter' => 2,
            'counts' => [$this->maths->id => 3],
        ])->assertSessionHasNoErrors();

        $exams = Exam::all();
        $this->assertCount(3, $exams);

        $all = $exams->flatMap(fn (Exam $exam) => $exam->questions->pluck('id'));

        $this->assertCount(9, $all);
        $this->assertCount(9, $all->unique(), 'Variantlarda eyni sual təkrarlanıb');

        // Başlıqlara variant hərfi əlavə olunur
        $this->assertSame(
            ['I qrup RK sınağı (A)', 'I qrup RK sınağı (B)', 'I qrup RK sınağı (C)'],
            $exams->pluck('title')->sort()->values()->all()
        );
    }

    /** Bank çatmayanda heç nə yaradılmır və çatışmazlıq göstərilir. */
    public function test_it_reports_the_shortfall_and_creates_nothing(): void
    {
        $this->bank($this->maths, 4, $this->q2);

        $response = $this->generate([
            'variants' => 2,
            'quarter' => 2,
            'counts' => [$this->maths->id => 3],
        ]);

        $response->assertSessionHasErrors('bank');

        $message = session('errors')->first('bank');
        $this->assertStringContainsString('Riyaziyyat', $message);
        // 2 variant × 3 sual = 6 lazımdır, bankda 4 var
        $this->assertStringContainsString('6', $message);
        $this->assertStringContainsString('4', $message);
        $this->assertStringContainsString('2 çatmır', $message);

        $this->assertSame(0, Exam::count());
    }

    public function test_the_shortfall_lists_every_missing_subject(): void
    {
        $this->bank($this->maths, 1);
        $this->bank($this->physics, 1);

        $response = $this->generate([
            'counts' => [$this->maths->id => 5, $this->physics->id => 5],
        ]);

        $message = session('errors')->first('bank');

        $this->assertStringContainsString('Riyaziyyat', $message);
        $this->assertStringContainsString('Fizika', $message);
        $this->assertSame(0, Exam::count());
    }

    /** Bir imtahana yalnız bir xarici dil düşə bilər. */
    public function test_only_one_language_can_be_chosen(): void
    {
        $english = Subject::factory()->create(['name' => 'İngilis dili', 'is_language' => true]);
        $russian = Subject::factory()->create(['name' => 'Rus dili', 'is_language' => true]);
        $this->category->subjects()->attach([$english->id, $russian->id]);

        $this->bank($english, 5);
        $this->bank($russian, 5);

        $this->generate([
            'counts' => [$english->id => 2, $russian->id => 2],
        ])->assertSessionHasErrors('counts');

        $this->assertSame(0, Exam::count());
    }

    public function test_a_subject_outside_the_category_is_rejected(): void
    {
        $foreign = Subject::factory()->create();
        $this->bank($foreign, 5);

        $this->generate(['counts' => [$foreign->id => 2]])->assertSessionHasErrors('counts');

        $this->assertSame(0, Exam::count());
    }

    // ---- Sualın əvəz edilməsi ----

    public function test_an_admin_can_replace_a_question_with_another_from_the_pool(): void
    {
        $this->bank($this->maths, 4, $this->q2);

        $this->generate(['quarter' => 2, 'counts' => [$this->maths->id => 3]]);

        $exam = Exam::firstOrFail();
        $question = $exam->questions()->first();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.replace', [$exam, $question]))
            ->assertSessionHasNoErrors();

        $exam->refresh();

        $this->assertSame(3, $exam->questions()->count());
        $this->assertFalse($exam->questions->contains('id', $question->id));
    }

    /** Dərc olunmuş imtahanın sualları sabit qalır. */
    public function test_a_published_exam_can_not_have_its_questions_replaced(): void
    {
        $this->bank($this->maths, 6, $this->q2);
        $this->generate(['quarter' => 2, 'counts' => [$this->maths->id => 3]]);

        $exam = Exam::firstOrFail();
        $exam->update(['is_published' => true, 'is_active' => true]);
        $question = $exam->questions()->first();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.replace', [$exam, $question]))
            ->assertSessionHasErrors('question');

        $this->assertTrue($exam->refresh()->questions->contains('id', $question->id));
    }

    public function test_students_can_not_generate_exams(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student, 'student')
            ->get(route('admin.exams.generate'))
            ->assertRedirect(route('admin.login'));
    }
}
