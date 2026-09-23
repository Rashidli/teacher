<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\QuestionService;
use App\Support\Sector;
use Database\Seeders\CategorySeeder;
use Database\Seeders\GroupSeeder;
use Database\Seeders\SubjectGroupScoreSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Tədris sektoru (az/ru) interfeys dilindən AYRIDIR:
 *  - daxil olmuş istifadəçi öz `users.sector` məzmununu görür, URL dili buna təsir etmir;
 *  - qonaq üçün defolt URL dilindən gəlir, kataloqdan dəyişilə bilər (sessiyada saxlanılır);
 *  - imtahanın sektoru ilə suallarının dili uyğun olmalıdır.
 */
class SectorTest extends TestCase
{
    use RefreshDatabase;

    private function seedTree(): void
    {
        $this->seed(SubjectSeeder::class);
        $this->seed(GroupSeeder::class);
        $this->seed(SubjectGroupScoreSeeder::class);
        $this->seed(CategorySeeder::class);
    }

    // ---- İmtahan sektoru ↔ sual dili ----

    /** Servis səviyyəsində: ru imtahanına az sualı bağlanmır. */
    public function test_a_question_in_the_other_language_cannot_be_attached(): void
    {
        $exam = Exam::factory()->russian()->create();
        $question = Question::factory()->create(['language' => Sector::AZ]);

        try {
            app(QuestionService::class)->attach($exam, $question);
            $this->fail('Sektoru uyğun gəlməyən sual bağlandı');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('question_id', $exception->errors());
        }

        $this->assertSame(0, $exam->questions()->count());
    }

    /** Admin panelindən (bankdan "əlavə et") də bloklanır. */
    public function test_attaching_from_the_bank_is_blocked_for_the_other_sector(): void
    {
        $admin = User::factory()->admin()->create();
        $exam = Exam::factory()->russian()->create();
        $question = Question::factory()->create(['language' => Sector::AZ]);

        $this->actingAs($admin)
            ->post(route('admin.exams.questions.attach', $exam), ['question_id' => $question->id])
            ->assertSessionHasErrors('question_id');

        $this->assertSame(0, $exam->questions()->count());
    }

    public function test_a_question_in_the_same_language_attaches(): void
    {
        $exam = Exam::factory()->russian()->create();
        $question = Question::factory()->russian()->create();

        app(QuestionService::class)->attach($exam, $question);

        $this->assertSame(1, $exam->questions()->count());
    }

    /** İmtahanda yaradılan sual imtahanın sektorunun dilində yaranır. */
    public function test_a_question_created_inside_an_exam_takes_the_exam_sector(): void
    {
        $admin = User::factory()->admin()->create();
        $subject = Subject::factory()->create();
        $exam = Exam::factory()->russian()->create([
            'subject_id' => $subject->id,
            'options_per_question' => 4,
        ]);

        $this->actingAs($admin)->post(route('admin.exams.questions.store', $exam), [
            'question_text' => 'Вопрос',
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'difficulty' => Question::DIFFICULTY_MEDIUM,
            'options' => [
                ['option_letter' => 'A', 'option_text' => 'А', 'is_correct' => true],
                ['option_letter' => 'B', 'option_text' => 'Б', 'is_correct' => false],
                ['option_letter' => 'C', 'option_text' => 'В', 'is_correct' => false],
                ['option_letter' => 'D', 'option_text' => 'Г', 'is_correct' => false],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(Sector::RU, $exam->questions()->firstOrFail()->language);
    }

    /** Sual bağlandıqdan sonra imtahanın sektoru dəyişdirilmir. */
    public function test_the_sector_cannot_change_once_questions_are_attached(): void
    {
        $admin = User::factory()->admin()->create();
        $exam = Exam::factory()->create(['sector' => Sector::AZ]);
        app(QuestionService::class)->attach($exam, Question::factory()->create());

        $this->actingAs($admin)->patch(route('admin.exams.update', $exam), [
            'title' => $exam->title,
            'duration_minutes' => $exam->duration_minutes,
            'is_free' => true,
            'sector' => Sector::RU,
        ])->assertSessionHasErrors('sector');

        $this->assertSame(Sector::AZ, $exam->fresh()->sector);
    }

    // ---- Kataloq ----

    /** Qonaq: /ru səhifəsində rus sektoru imtahanları görünür. */
    public function test_a_guest_on_the_russian_page_sees_russian_sector_exams(): void
    {
        $this->seedTree();
        $category = Category::where('path', 'abituriyent')->firstOrFail();

        Exam::factory()->published()->russian()->create([
            'category_id' => $category->id, 'title' => 'Русский экзамен',
        ]);
        Exam::factory()->published()->create([
            'category_id' => $category->id, 'title' => 'Azərbaycan imtahanı',
        ]);

        $this->get('/ru/abiturient')->assertInertia(fn ($page) => $page
            ->where('sector', Sector::RU)
            ->has('exams', 1)
            ->where('exams.0.slug', \Illuminate\Support\Str::slug('Русский экзамен')));

        $this->get('/abituriyent')->assertInertia(fn ($page) => $page
            ->where('sector', Sector::AZ)
            ->has('exams', 1)
            ->where('exams.0.slug', \Illuminate\Support\Str::slug('Azərbaycan imtahanı')));
    }

    /** Daxil olmuş şagird: sektor URL dilindən yox, profildən gəlir. */
    public function test_a_logged_in_student_sees_their_own_sector_on_the_russian_page(): void
    {
        $this->seedTree();
        $category = Category::where('path', 'abituriyent')->firstOrFail();

        Exam::factory()->published()->russian()->create([
            'category_id' => $category->id, 'title' => 'Русский экзамен',
        ]);
        Exam::factory()->published()->create([
            'category_id' => $category->id, 'title' => 'Azərbaycan imtahanı',
        ]);

        $student = User::factory()->student()->create(['sector' => Sector::AZ]);

        $this->actingAs($student)
            ->get('/ru/abiturient')
            ->assertInertia(fn ($page) => $page
                ->where('sector', Sector::AZ)
                ->where('canSwitchSector', false)
                ->has('exams', 1)
                ->where('exams.0.slug', \Illuminate\Support\Str::slug('Azərbaycan imtahanı')));
    }

    /** Qonağın seçimi sessiyada qalır və sonrakı səhifələrdə də işləyir. */
    public function test_a_guest_can_switch_the_sector(): void
    {
        $this->seedTree();

        $this->post(route('sector.update'), ['sector' => Sector::RU])->assertRedirect();

        $this->assertSame(Sector::RU, session(Sector::SESSION_KEY));

        // Azərbaycan dilli səhifədə də seçim qüvvədədir
        $this->get('/abituriyent')->assertInertia(fn ($page) => $page->where('sector', Sector::RU));
    }

    /** Daxil olmuş istifadəçinin sektoru sessiya ilə dəyişmir (profildən idarə olunur). */
    public function test_a_logged_in_student_cannot_switch_the_sector_from_the_catalogue(): void
    {
        $this->seedTree();
        $student = User::factory()->student()->create(['sector' => Sector::AZ]);

        $this->actingAs($student)
            ->post(route('sector.update'), ['sector' => Sector::RU])
            ->assertRedirect();

        $this->assertNull(session(Sector::SESSION_KEY));
        $this->assertSame(Sector::AZ, $student->fresh()->sector);
    }

    // ---- Qeydiyyat və profil ----

    /** Rus interfeysində qeydiyyat formasında rus sektoru öncədən seçilir. */
    public function test_the_registration_form_preselects_the_sector_from_the_interface_language(): void
    {
        $this->get('/ru/register')->assertInertia(fn ($page) => $page
            ->component('Auth/Register')
            ->where('defaultSector', Sector::RU));

        $this->get('/register')->assertInertia(fn ($page) => $page
            ->where('defaultSector', Sector::AZ));
    }

    /** Qonağın kataloqdakı seçimi qeydiyyatda defolt olur. */
    public function test_the_catalogue_choice_becomes_the_registration_default(): void
    {
        $this->post(route('sector.update'), ['sector' => Sector::RU]);

        $this->get('/register')->assertInertia(fn ($page) => $page->where('defaultSector', Sector::RU));
    }

    public function test_registration_stores_the_chosen_sector(): void
    {
        $this->post('/register', [
            'first_name' => 'Rəşid',
            'last_name' => 'Əliyev',
            'email' => 'sektor@example.test',
            'phone' => '+994501234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'sector' => Sector::RU,
            'terms' => true,
        ])->assertRedirect(route('student.dashboard', absolute: false));

        $this->assertSame(Sector::RU, User::where('email', 'sektor@example.test')->firstOrFail()->sector);
    }

    public function test_registration_rejects_an_unknown_sector(): void
    {
        $this->post('/register', [
            'first_name' => 'Rəşid',
            'last_name' => 'Əliyev',
            'email' => 'sektor2@example.test',
            'phone' => '+994501234568',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'sector' => 'en',
            'terms' => true,
        ])->assertSessionHasErrors('sector');
    }

    /** Sektor profildən dəyişir; alınmış imtahanlara giriş imtahana bağlıdır, dəyişmir. */
    public function test_a_student_can_change_the_sector_from_the_profile(): void
    {
        $student = User::factory()->student()->create(['sector' => Sector::AZ]);

        $this->actingAs($student)->patch(route('profile.update'), [
            'name' => $student->name,
            'email' => $student->email,
            'sector' => Sector::RU,
        ])->assertRedirect(route('profile.edit'));

        $this->assertSame(Sector::RU, $student->fresh()->sector);
    }

    // ---- İctimai imtahan səhifəsi ----

    public function test_an_exam_from_the_other_sector_returns_not_found(): void
    {
        $student = User::factory()->student()->create(['sector' => Sector::AZ]);
        $exam = Exam::factory()->published()->russian()->create();

        $this->actingAs($student)
            ->get($exam->publicUrl())
            ->assertNotFound();
    }

    /** Öz sektorundakı imtahan normal açılır. */
    public function test_an_exam_of_the_students_own_sector_opens(): void
    {
        $student = User::factory()->student()->create(['sector' => Sector::RU]);
        $exam = Exam::factory()->published()->russian()->create(['title' => 'Русский экзамен']);

        $this->actingAs($student)
            ->get($exam->publicUrl())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('exam.title', 'Русский экзамен'));
    }

    // ---- Kateqoriya ↔ fənn (ana dili) ----

    /** I mərhələdə ana dili sektora görə dəyişir. */
    public function test_the_native_language_subject_depends_on_the_sector(): void
    {
        $this->seedTree();

        $category = Category::where('path', 'abituriyent/1-ci-merhele')->firstOrFail();

        $az = $category->subjectsForSector(Sector::AZ)->pluck('slug')->all();
        $ru = $category->subjectsForSector(Sector::RU)->pluck('slug')->all();

        $this->assertContains('azerbaycan-dili', $az);
        $this->assertNotContains('rus-dili', array_slice($az, 0, 1), 'az sektorunda ana dili Azərbaycan dilidir');

        $this->assertSame('rus-dili', $ru[0], 'ru sektorunda ana dili Rus dilidir');
        $this->assertNotContains('rus-dili', array_slice($ru, 1), 'Rus dili xarici dil kimi təkrarlanmır');
    }

    /** Pivotda sector = null olan fənn hər iki sektorda görünür. */
    public function test_a_subject_without_a_sector_belongs_to_both(): void
    {
        $this->seedTree();

        $category = Category::where('path', 'abituriyent/2-ci-qrup')->firstOrFail();

        $this->assertSame(
            $category->subjectsForSector(Sector::AZ)->pluck('slug')->all(),
            $category->subjectsForSector(Sector::RU)->pluck('slug')->all()
        );
    }

    /** Rus sektoru bayrağı ağacda aşağı ötürülür. */
    public function test_the_russian_flag_is_inherited_by_children(): void
    {
        $this->seedTree();

        $this->assertTrue(Category::where('path', 'abituriyent')->firstOrFail()->ru_enabled);
        $this->assertTrue(Category::where('path', 'abituriyent/1-ci-qrup/rk')->firstOrFail()->ru_enabled);
        $this->assertFalse(Category::where('path', 'suruculuk-imtahani')->firstOrFail()->ru_enabled);
    }
}
